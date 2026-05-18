<?php

declare(strict_types=1);

namespace Horde\Turba\Responsive;

use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Config\RegistryState;
use Horde\Core\View\ResponsiveTemplateView;
use Horde\Date\Formatter\IcuFormatter;
use Horde_Date;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Responsive Contacts Controller
 *
 * Modern mobile-first contacts browsing interface.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/apache ASL
 * @package   Turba
 */
class ResponsiveController implements RequestHandlerInterface
{
    /**
     * Handle request
     *
     * @param ServerRequestInterface $request The request object
     *
     * @return ResponseInterface The response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        global $injector;

        // Get route parameters
        $route = $request->getAttribute('route', []);

        // Determine action based on route
        if (isset($route['source']) && isset($route['key'])) {
            // Check if this is an export request
            $path = $request->getUri()->getPath();
            if (str_contains($path, '/responsive/export/')) {
                return $this->exportContact($request, $route['source'], $route['key']);
            }
            return $this->viewContact($request, $route['source'], $route['key']);
        }

        // Check if this is an add contact request
        $path = $request->getUri()->getPath();
        if (str_contains($path, '/responsive/add')) {
            return $this->addContact($request);
        }

        return $this->index($request);
    }

    /**
     * Display contacts browse list
     *
     * @param ServerRequestInterface $request The request object
     *
     * @return ResponseInterface The response
     */
    private function index(ServerRequestInterface $request): ResponseInterface
    {
        global $registry, $injector, $browse_source_count;

        $responsiveAssets = new ResponsiveAssets(new RegistryState($registry->applications));

        // Load contacts from all browseable sources
        $contactList = [];
        if ($browse_source_count) {
            foreach (\Turba::getAddressBooks() as $key => $val) {
                if (!empty($val['browse'])) {
                    try {
                        $driver = $injector->getInstance('Turba_Factory_Driver')->create($key);
                    } catch (\Turba_Exception $e) {
                        continue;
                    }

                    try {
                        $contacts = $driver->search([], null, 'AND', ['__key', 'name', 'email', 'cellPhone', 'workPhone', 'homePhone']);
                        $contacts->reset();
                    } catch (\Turba_Exception $e) {
                        continue;
                    }

                    $sourceContacts = [];
                    while ($contact = $contacts->next()) {
                        $name = \Turba::formatName($contact);

                        // Determine primary phone for call action
                        $cellPhone = $contact->getValue('cellPhone');
                        $workPhone = $contact->getValue('workPhone');
                        $homePhone = $contact->getValue('homePhone');
                        $primaryPhone = $cellPhone ?: $workPhone ?: $homePhone;

                        $sourceContacts[] = [
                            'key' => $contact->getValue('__key'),
                            'name' => strlen($name) ? $name : ('[' . _("No Name") . ']'),
                            'email' => $contact->getValue('email') ?: '',
                            'phone' => $primaryPhone ?: '',
                            'isGroup' => $contact->isGroup(),
                        ];
                    }

                    // Always add the address book, even if empty
                    $contactList[$key] = [
                        'title' => $val['title'],
                        'contacts' => $sourceContacts,
                    ];
                }
            }
        }

        // Build topbar
        $topbar = $this->renderTopbar();

        // Prepare view data
        $viewData = [
            'topbar' => $topbar,
            'contactList' => $contactList,
            'hasContacts' => !empty($contactList),
            'cssUrls' => $responsiveAssets->getCssUrls('turba'),
            'jsUrls' => array_merge(
                $responsiveAssets->getJsUrls('horde', ['responsive-topbar.js']),
                $responsiveAssets->getJsUrls('turba', ['responsive.js'])
            ),
            'groupIconUrl' => $registry->get('themesuri', 'turba') . '/default/graphics/group.png',
        ];

        // Render template
        $templatePath = TURBA_TEMPLATES . '/responsive/browse.html.php';
        $view = new ResponsiveTemplateView($templatePath, $viewData);

        $streamFactory = $injector->getInstance('Psr\Http\Message\StreamFactoryInterface');
        $responseFactory = $injector->getInstance('Psr\Http\Message\ResponseFactoryInterface');

        return $responseFactory->createResponse(200)
            ->withBody($streamFactory->createStream($view->render()));
    }

    /**
     * Display contact detail
     *
     * @param ServerRequestInterface $request The request object
     * @param string $source Address book source
     * @param string $key Contact key
     *
     * @return ResponseInterface The response
     */
    private function viewContact(ServerRequestInterface $request, string $source, string $key): ResponseInterface
    {
        global $registry, $injector, $notification, $cfgSources, $attributes;

        $responsiveAssets = new ResponsiveAssets(new RegistryState($registry->applications));

        // Validate source
        if (!isset($cfgSources[$source])) {
            $notification->push(_("The contact you requested does not exist."), 'horde.error');
            return $this->redirectToBrowse();
        }

        // Load contact
        try {
            $driver = $injector->getInstance('Turba_Factory_Driver')->create($source);
            $contact = $driver->getObject($key);
        } catch (\Horde_Exception $e) {
            $notification->push(_("Addressbook entry could not be loaded."), 'horde.error');
            return $this->redirectToBrowse();
        }

        // Check permissions
        if (!$contact->hasPermission(\Horde_Perms::READ)) {
            $notification->push(_("You do not have permission to view this contact."), 'horde.error');
            return $this->redirectToBrowse();
        }

        // Build contact data structure
        $contactData = $this->buildContactData($contact, $source);

        // Build topbar
        $topbar = $this->renderTopbar();

        // Prepare view data
        $viewData = [
            'topbar' => $topbar,
            'contact' => $contactData,
            'backUrl' => \Horde::url('responsive', true),
            'cssUrls' => $responsiveAssets->getCssUrls('turba'),
            'jsUrls' => $responsiveAssets->getJsUrls('horde', ['responsive-topbar.js']),
        ];

        // Render template
        $templatePath = TURBA_TEMPLATES . '/responsive/contact.html.php';
        $view = new ResponsiveTemplateView($templatePath, $viewData);

        $streamFactory = $injector->getInstance('Psr\Http\Message\StreamFactoryInterface');
        $responseFactory = $injector->getInstance('Psr\Http\Message\ResponseFactoryInterface');

        return $responseFactory->createResponse(200)
            ->withBody($streamFactory->createStream($view->render()));
    }

    /**
     * Build contact data structure for template
     *
     * @param object $contact Turba_Object
     * @param string $source Source key
     *
     * @return array Contact data
     */
    private function buildContactData($contact, string $source): array
    {
        global $attributes, $registry;

        $data = [
            'name' => \Turba::formatName($contact),
            'source' => $source,
            'key' => $contact->getValue('__key'),
            'isGroup' => $contact->isGroup(),
            'sections' => [],
            'photo' => null,
            'groupMembers' => [],
            'quickActions' => [],
        ];

        // Get photo if available
        if ($contact->hasValue('photo')) {
            $photo = $contact->getValue('photo');
            if (is_array($photo) && isset($photo['load']['data'])) {
                $data['photo'] = 'data:image/jpeg;base64,' . base64_encode($photo['load']['data']);
            }
        }

        // Organize fields by tabs/sections
        $tabs = $contact->driver->tabs;
        if (!count($tabs)) {
            $tabs = [
                _("Contact Information") => array_keys($contact->driver->getCriteria()),
            ];
        }

        foreach ($tabs as $tabName => $fields) {
            $sectionFields = [];

            foreach ($fields as $fieldName) {
                $value = $contact->getValue($fieldName);
                if (!strlen((string)$value)) {
                    continue;
                }

                $field = [
                    'label' => $attributes[$fieldName]['label'] ?? $fieldName,
                    'value' => $value,
                    'link' => null,
                    'type' => 'text',
                ];

                // Add action links and formatting for specific field types
                switch ($fieldName) {
                    case 'birthday':
                    case 'anniversary':
                        $field['type'] = 'date';
                        if (isset($attributes[$fieldName]['params']['format_out'])) {
                            $formatter = new IcuFormatter();
                            $locale = $GLOBALS['language'] ?? 'en_US';
                            try {
                                $date = new Horde_Date($value);
                                $field['value'] = $formatter->format(
                                    $date->toDateTime(),
                                    $attributes[$fieldName]['params']['format_out'],
                                    $locale
                                );
                            } catch (\Exception $e) {
                            }
                        }
                        break;

                    case 'email':
                    case 'emails':
                        $field['type'] = 'email';
                        // Try to get IMP compose URL
                        try {
                            $field['link'] = (string) $registry->call('mail/compose', [
                                ['to' => $value],
                            ]);
                        } catch (\Horde_Exception $e) {
                            // Fallback to mailto
                            $field['link'] = 'mailto:' . urlencode($value);
                        }
                        break;

                    case 'homePhone':
                    case 'workPhone':
                    case 'cellPhone':
                    case 'fax':
                        $field['type'] = 'phone';
                        $field['link'] = 'tel:' . preg_replace('/[^\d+]/', '', $value);
                        break;

                    case 'homeAddress':
                    case 'workAddress':
                        $field['type'] = 'address';
                        // Could add map link in future
                        break;

                    case 'photo':
                        // Skip photo field in sections (displayed separately)
                        continue 2;
                }

                $sectionFields[] = $field;
            }

            if (!empty($sectionFields)) {
                $data['sections'][$tabName] = $sectionFields;
            }
        }

        // Build quick actions (for non-group contacts)
        if (!$contact->isGroup()) {
            // Email action
            $email = $contact->getValue('email');
            if ($email) {
                try {
                    $composeUrl = (string) $registry->call('mail/compose', [['to' => $email]]);
                    $data['quickActions'][] = [
                        'icon' => '✉️',
                        'label' => _("Email"),
                        'url' => $composeUrl,
                        'type' => 'email',
                    ];
                } catch (\Horde_Exception $e) {
                    $data['quickActions'][] = [
                        'icon' => '✉️',
                        'label' => _("Email"),
                        'url' => 'mailto:' . urlencode($email),
                        'type' => 'email',
                    ];
                }
            }

            // Phone actions
            $cellPhone = $contact->getValue('cellPhone');
            $workPhone = $contact->getValue('workPhone');
            $homePhone = $contact->getValue('homePhone');

            $primaryPhone = $cellPhone ?: $workPhone ?: $homePhone;
            if ($primaryPhone) {
                $cleanPhone = preg_replace('/[^\d+]/', '', $primaryPhone);
                $data['quickActions'][] = [
                    'icon' => '📞',
                    'label' => _("Call"),
                    'url' => 'tel:' . $cleanPhone,
                    'type' => 'phone',
                ];

                // SMS action for cell phones only
                if ($cellPhone) {
                    $data['quickActions'][] = [
                        'icon' => '💬',
                        'label' => _("Text"),
                        'url' => 'sms:' . preg_replace('/[^\d+]/', '', $cellPhone),
                        'type' => 'sms',
                    ];
                }
            }

            // Map action
            $address = $contact->getValue('workAddress') ?: $contact->getValue('homeAddress');
            if ($address) {
                // Format address for Google Maps query
                $mapQuery = urlencode(str_replace("\n", ', ', $address));
                $data['quickActions'][] = [
                    'icon' => '📍',
                    'label' => _("Map"),
                    'url' => 'https://www.google.com/maps/search/?api=1&query=' . $mapQuery,
                    'type' => 'map',
                ];
            }

            // vCard export action
            $data['quickActions'][] = [
                'icon' => '💾',
                'label' => _("Export"),
                'url' => (string) \Horde::url('responsive/export/' . $source . '/' . $contact->getValue('__key'), true),
                'type' => 'export',
            ];
        }

        // Get group members if this is a group
        if ($contact->isGroup()) {
            try {
                $members = $contact->listMembers();
                $members->reset();

                while ($member = $members->next()) {
                    $memberName = \Turba::formatName($member);
                    $data['groupMembers'][] = [
                        'name' => strlen($memberName) ? $memberName : '[' . _("No Name") . ']',
                        'url' => \Horde::url('responsive/contact/' . $member->getSource() . '/' . $member->getValue('__key'), true),
                    ];
                }
            } catch (\Turba_Exception $e) {
                // Ignore member loading errors
            }
        }

        return $data;
    }

    /**
     * Export contact as vCard
     *
     * @param ServerRequestInterface $request The request object
     * @param string $source Address book source
     * @param string $key Contact key
     *
     * @return ResponseInterface The response
     */
    private function exportContact(ServerRequestInterface $request, string $source, string $key): ResponseInterface
    {
        global $injector, $notification, $cfgSources;

        // Validate source
        if (!isset($cfgSources[$source])) {
            $notification->push(_("The contact you requested does not exist."), 'horde.error');
            return $this->redirectToBrowse();
        }

        // Load contact
        try {
            $driver = $injector->getInstance('Turba_Factory_Driver')->create($source);
            $contact = $driver->getObject($key);
        } catch (\Horde_Exception $e) {
            $notification->push(_("Contact could not be loaded."), 'horde.error');
            return $this->redirectToBrowse();
        }

        // Check permissions
        if (!$contact->hasPermission(\Horde_Perms::READ)) {
            $notification->push(_("You do not have permission to view this contact."), 'horde.error');
            return $this->redirectToBrowse();
        }

        // Generate vCard
        $vcard = $driver->tovCard($contact, '3.0', null, true);
        $filename = preg_replace('/[^\w\s\-]/', '', \Turba::formatName($contact)) . '.vcf';
        $filename = $filename ?: 'contact.vcf';

        $streamFactory = $injector->getInstance('Psr\\Http\\Message\\StreamFactoryInterface');
        $responseFactory = $injector->getInstance('Psr\\Http\\Message\\ResponseFactoryInterface');

        return $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/vcard; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withBody($streamFactory->createStream($vcard->exportvCalendar()));
    }

    /**
     * Add new contact form and handler
     *
     * @param ServerRequestInterface $request The request object
     *
     * @return ResponseInterface The response
     */
    private function addContact(ServerRequestInterface $request): ResponseInterface
    {
        global $registry, $injector, $notification, $browse_source_count;

        $responsiveAssets = new ResponsiveAssets(new RegistryState($registry->applications));

        // Get writable address books
        $writableSources = [];
        if ($browse_source_count) {
            foreach (\Turba::getAddressBooks() as $key => $val) {
                if (!empty($val['browse']) && $val['type'] !== 'vcard') {
                    try {
                        $driver = $injector->getInstance('Turba_Factory_Driver')->create($key);
                        if ($driver->hasPermission(\Horde_Perms::EDIT)) {
                            $writableSources[$key] = $val['title'];
                        }
                    } catch (\Turba_Exception $e) {
                        continue;
                    }
                }
            }
        }

        // Handle form submission (POST request)
        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $source = $body['source'] ?? '';
            $name = trim($body['name'] ?? '');
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');

            // Validate
            $errors = [];
            if (!$source || !isset($writableSources[$source])) {
                $errors[] = _("Please select a valid address book.");
            }
            if (!$name) {
                $errors[] = _("Name is required.");
            }

            if (empty($errors)) {
                try {
                    $driver = $injector->getInstance('Turba_Factory_Driver')->create($source);

                    // Create contact with minimal fields
                    $attributes = ['name' => $name];
                    if ($email) {
                        $attributes['email'] = $email;
                    }
                    if ($phone) {
                        $attributes['cellPhone'] = $phone;
                    }

                    $result = $driver->add($attributes);

                    $notification->push(_("Contact added successfully."), 'horde.success');

                    // Redirect to the new contact
                    $responseFactory = $injector->getInstance('Psr\\Http\\Message\\ResponseFactoryInterface');
                    return $responseFactory->createResponse(302)
                        ->withHeader('Location', (string) \Horde::url('responsive/contact/' . $source . '/' . $result, true));
                } catch (\Turba_Exception $e) {
                    $errors[] = sprintf(_("Failed to add contact: %s"), $e->getMessage());
                }
            }

            // Show errors
            foreach ($errors as $error) {
                $notification->push($error, 'horde.error');
            }
        }

        // Build topbar
        $topbar = $this->renderTopbar();

        // Prepare view data
        $viewData = [
            'topbar' => $topbar,
            'writableSources' => $writableSources,
            'hasWritableSources' => !empty($writableSources),
            'backUrl' => \Horde::url('responsive', true),
            'cssUrls' => $responsiveAssets->getCssUrls('turba'),
            'jsUrls' => $responsiveAssets->getJsUrls('horde', ['responsive-topbar.js']),
        ];

        // Render template
        $templatePath = TURBA_TEMPLATES . '/responsive/add.html.php';
        $view = new ResponsiveTemplateView($templatePath, $viewData);

        $streamFactory = $injector->getInstance('Psr\\Http\\Message\\StreamFactoryInterface');
        $responseFactory = $injector->getInstance('Psr\\Http\\Message\\ResponseFactoryInterface');

        return $responseFactory->createResponse(200)
            ->withBody($streamFactory->createStream($view->render()));
    }

    /**
     * Redirect to browse page
     *
     * @return ResponseInterface The response
     */
    private function redirectToBrowse(): ResponseInterface
    {
        global $injector;

        $responseFactory = $injector->getInstance('Psr\Http\Message\ResponseFactoryInterface');
        return $responseFactory->createResponse(302)
            ->withHeader('Location', (string) \Horde::url('responsive', true));
    }

    /**
     * Render the responsive topbar
     *
     * @return string HTML topbar
     */
    private function renderTopbar(): string
    {
        global $registry;

        // Get all active apps
        $allApps = $registry->listApps(['active', 'admin', 'noadmin', 'topbar'], true, null);

        // Separate apps into top-level and submenu items
        $topLevelApps = [];
        $allAppsList = [];

        foreach ($allApps as $app => $params) {
            // Skip horde itself
            if ($app === 'horde') {
                continue;
            }

            // Skip topbar-only items (they're app-specific widgets)
            // IMPORTANT: Must check this BEFORE hasPermission()
            // because topbar items like 'kronolith-menu' don't have APIs
            if ($params['status'] === 'topbar') {
                continue;
            }

            // Skip if user doesn't have permission
            if (!$registry->hasPermission($app, \Horde_Perms::SHOW)) {
                continue;
            }

            $appData = [
                'name' => strlen($params['name'] ?? '') ? _($params['name']) : '',
                'url' => (string) \Horde::url($registry->getInitialPage($app), true, ['app' => $app]),
                'icon' => $params['icon'] ?? $registry->get('icon', $app),
                'app' => $app, // Add app identifier for CSS class
            ];

            // Add to all apps list (for hamburger menu)
            $allAppsList[] = $appData;

            // Top-level apps (no menu_parent) go to topbar
            if (empty($params['menu_parent'])) {
                $topLevelApps[] = $appData;
            }
        }

        $topbarData = [
            'appName' => _("Contacts"),
            'portalUrl' => (string) $registry->getServiceLink('portal')->setRaw(true),
            'logoutUrl' => (string) $registry->getServiceLink('logout')->setRaw(true),
            'userName' => $registry->getAuth(),
            'topLevelApps' => $topLevelApps,
            'allApps' => $allAppsList,
        ];

        $templatePath = HORDE_TEMPLATES . '/responsive/topbar.html.php';
        $view = new ResponsiveTemplateView($templatePath, $topbarData);

        return $view->render();
    }
}
