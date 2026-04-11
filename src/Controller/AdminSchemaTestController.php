<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 */

namespace Horde\Turba\Controller;

use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Turba;
use Turba_Driver;
use Turba_Exception;
use Turba_Object;

/**
 * Admin diagnostic tool for inspecting address book backends.
 *
 * Bypasses the display/form layer entirely and shows raw backend data
 * in a technical format. Useful for diagnosing LDAP type mismatches,
 * field mapping issues, and other backend problems.
 *
 * Access restricted to Horde administrators. Not advertised in the sidebar.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class AdminSchemaTestController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private Horde_Notification_Handler $notification,
        private Horde_PageOutput $pageOutput,
        private Horde_Registry $registry,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAdmin()) {
            $this->notification->push(
                _("You are not allowed to access the admin area."),
                'horde.error'
            );
            return $this->redirect(
                (string) \Horde::url('', true)
            );
        }

        $params = $request->getQueryParams();
        $source = $params['source'] ?? null;

        if ($source !== null) {
            return $this->viewSource($source, $params);
        }

        return $this->listSources();
    }

    private function isAdmin(): bool
    {
        return $this->registry->isAdmin()
            || $this->registry->isAdmin(['permission' => 'turba:admin']);
    }

    private function listSources(): ResponseInterface
    {
        $cfgSources = $GLOBALS['cfgSources'];
        $webroot = rtrim((string) $this->registry->get('webroot', 'turba'), '/');

        $html = $this->renderChrome(
            _("Admin: Schema Test"),
            function () use ($cfgSources, $webroot) {
                echo '<h1 class="header">' . $this->escapeHtml(_("Address Book Backend Diagnostics")) . '</h1>';
                echo '<p class="horde-content">'
                    . $this->escapeHtml(_("Inspect backend field mappings and raw contact data. Select a source to view details."))
                    . '</p>';

                echo '<table class="horde-table sortable">';
                echo '<thead><tr>';
                echo '<th>' . $this->escapeHtml(_("Source Key")) . '</th>';
                echo '<th>' . $this->escapeHtml(_("Title")) . '</th>';
                echo '<th>' . $this->escapeHtml(_("Type")) . '</th>';
                echo '<th>' . $this->escapeHtml(_("Fields")) . '</th>';
                echo '<th>' . $this->escapeHtml(_("Inspect")) . '</th>';
                echo '</tr></thead>';
                echo '<tbody>';

                foreach ($cfgSources as $key => $cfg) {
                    $type = $cfg['type'] ?? 'unknown';
                    $fieldCount = 0;
                    if (isset($cfg['map'])) {
                        foreach ($cfg['map'] as $v) {
                            if (!is_array($v)) {
                                $fieldCount++;
                            }
                        }
                    }

                    $url = $webroot . '/admin/schematest?source=' . urlencode($key);

                    echo '<tr>';
                    echo '<td><code>' . $this->escapeHtml($key) . '</code></td>';
                    echo '<td>' . $this->escapeHtml($cfg['title'] ?? '') . '</td>';
                    echo '<td><code>' . $this->escapeHtml($type) . '</code></td>';
                    echo '<td>' . $fieldCount . '</td>';
                    echo '<td><a href="' . $this->escapeHtml($url) . '">' . $this->escapeHtml(_("Inspect")) . '</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
        );

        return $this->htmlResponse($html);
    }

    private function viewSource(string $sourceKey, array $params): ResponseInterface
    {
        $cfgSources = $GLOBALS['cfgSources'];
        $webroot = rtrim((string) $this->registry->get('webroot', 'turba'), '/');

        if (!isset($cfgSources[$sourceKey])) {
            $this->notification->push(
                sprintf(_("Source \"%s\" does not exist."), $sourceKey),
                'horde.error'
            );
            return $this->redirect($webroot . '/admin/schematest');
        }

        $limit = min((int) ($params['limit'] ?? 5), 50);
        if ($limit < 1) {
            $limit = 5;
        }

        $html = $this->renderChrome(
            sprintf(_("Admin: Schema Test — %s"), $cfgSources[$sourceKey]['title'] ?? $sourceKey),
            function () use ($sourceKey, $cfgSources, $webroot, $limit) {
                $cfg = $cfgSources[$sourceKey];

                $backUrl = $webroot . '/admin/schematest';
                echo '<p class="horde-content"><a href="' . $this->escapeHtml($backUrl) . '">&laquo; '
                    . $this->escapeHtml(_("Back to source list")) . '</a></p>';

                // Section 1: Source info
                $this->renderSourceInfo($sourceKey, $cfg);

                // Section 2: Field mapping
                $this->renderFieldMapping($cfg);

                // Section 3: Driver parameters (redacted)
                $this->renderDriverParams($cfg);

                // Section 4: Tabs configuration
                if (!empty($cfg['tabs'])) {
                    $this->renderTabs($cfg);
                }

                // Section 5: Sample contacts
                $this->renderSampleContacts($sourceKey, $limit, $webroot);
            }
        );

        return $this->htmlResponse($html);
    }

    private function renderSourceInfo(string $sourceKey, array $cfg): void
    {
        echo '<h1 class="header">' . $this->escapeHtml(_("Source Information")) . '</h1>';
        echo '<table class="horde-table">';

        $rows = [
            _("Key") => $sourceKey,
            _("Title") => $cfg['title'] ?? '',
            _("Type") => $cfg['type'] ?? 'unknown',
            _("Read-Only") => !empty($cfg['readonly']) ? _("Yes") : _("No"),
            _("Browse") => !empty($cfg['browse']) ? _("Yes") : _("No"),
        ];

        if (isset($cfg['params']['charset'])) {
            $rows[_("Charset")] = $cfg['params']['charset'];
        }
        if (isset($cfg['params']['scope'])) {
            $rows[_("LDAP Scope")] = $cfg['params']['scope'];
        }
        if (isset($cfg['params']['root'])) {
            $rows[_("LDAP Base DN")] = $cfg['params']['root'];
        }

        foreach ($rows as $label => $value) {
            echo '<tr>';
            echo '<td><strong>' . $this->escapeHtml($label) . '</strong></td>';
            echo '<td><code>' . $this->escapeHtml((string) $value) . '</code></td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    private function renderFieldMapping(array $cfg): void
    {
        global $attributes;

        echo '<h1 class="header">' . $this->escapeHtml(_("Field Mapping")) . '</h1>';
        echo '<table class="horde-table sortable">';
        echo '<thead><tr>';
        echo '<th>' . $this->escapeHtml(_("Turba Attribute")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Backend Field")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Kind")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Attribute Type")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Label")) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        $map = $cfg['map'] ?? [];
        ksort($map);

        foreach ($map as $turbaKey => $backendField) {
            $isComposite = is_array($backendField);
            $attrType = $attributes[$turbaKey]['type'] ?? '';
            $attrLabel = $attributes[$turbaKey]['label'] ?? '';

            echo '<tr>';
            echo '<td><code>' . $this->escapeHtml($turbaKey) . '</code></td>';

            if ($isComposite) {
                $fields = implode(', ', $backendField['fields'] ?? []);
                $format = $backendField['format'] ?? '';
                echo '<td><em>' . $this->escapeHtml(_("Composite")) . ':</em> '
                    . $this->escapeHtml($fields)
                    . '<br><small>format: <code>' . $this->escapeHtml($format) . '</code></small></td>';
                echo '<td>composite</td>';
            } else {
                echo '<td><code>' . $this->escapeHtml($backendField) . '</code></td>';
                echo '<td>scalar</td>';
            }

            echo '<td><code>' . $this->escapeHtml($attrType) . '</code></td>';
            echo '<td>' . $this->escapeHtml($attrLabel) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderDriverParams(array $cfg): void
    {
        $params = $cfg['params'] ?? [];
        if (empty($params)) {
            return;
        }

        // Redact sensitive values
        $redacted = ['bindpw', 'writepw', 'password', 'pass'];

        echo '<h1 class="header">' . $this->escapeHtml(_("Driver Parameters")) . '</h1>';
        echo '<table class="horde-table">';
        echo '<thead><tr>';
        echo '<th>' . $this->escapeHtml(_("Parameter")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Value")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("PHP Type")) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        ksort($params);
        foreach ($params as $key => $value) {
            // Skip objects (LDAP connections, DB adapters, etc.)
            if (is_object($value)) {
                $display = get_class($value);
                $type = 'object';
            } elseif (in_array($key, $redacted, true)) {
                $display = '********';
                $type = gettype($value);
            } elseif (is_array($value)) {
                $display = $this->truncate(var_export($value, true), 200);
                $type = 'array';
            } elseif (is_bool($value)) {
                $display = $value ? 'true' : 'false';
                $type = 'bool';
            } else {
                $display = (string) $value;
                $type = gettype($value);
            }

            echo '<tr>';
            echo '<td><code>' . $this->escapeHtml($key) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($display) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($type) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderTabs(array $cfg): void
    {
        echo '<h1 class="header">' . $this->escapeHtml(_("Tabs Configuration")) . '</h1>';
        echo '<table class="horde-table">';
        echo '<thead><tr>';
        echo '<th>' . $this->escapeHtml(_("Tab")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Fields")) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($cfg['tabs'] as $tabName => $fields) {
            echo '<tr>';
            echo '<td>' . $this->escapeHtml($tabName) . '</td>';
            echo '<td><code>' . $this->escapeHtml(implode(', ', $fields)) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderSampleContacts(string $sourceKey, int $limit, string $webroot): void
    {
        echo '<h1 class="header">'
            . sprintf($this->escapeHtml(_("Sample Contacts (limit: %d)")), $limit)
            . '</h1>';

        // Quick-nav for different limits
        $url = $webroot . '/admin/schematest?source=' . urlencode($sourceKey);
        echo '<p class="horde-content">' . $this->escapeHtml(_("Show:")) . ' ';
        foreach ([5, 10, 25, 50] as $n) {
            if ($n === $limit) {
                echo '<strong>' . $n . '</strong> ';
            } else {
                echo '<a href="' . $this->escapeHtml($url . '&limit=' . $n) . '">' . $n . '</a> ';
            }
        }
        echo '</p>';

        try {
            $driver = $GLOBALS['injector']
                ->getInstance('Turba_Factory_Driver')
                ->create($sourceKey);
        } catch (Turba_Exception $e) {
            echo '<p class="horde-error">'
                . $this->escapeHtml(sprintf(_("Failed to create driver: %s"), $e->getMessage()))
                . '</p>';
            return;
        }

        try {
            $results = $driver->search(
                [],
                null,
                'AND',
                array_values($driver->fields),
                [],
                false,
                false,
                false,
                $limit
            );
        } catch (Turba_Exception $e) {
            echo '<p class="horde-error">'
                . $this->escapeHtml(sprintf(_("Search failed: %s"), $e->getMessage()))
                . '</p>';
            return;
        }

        $results->reset();
        $count = 0;

        while ($contact = $results->next()) {
            $count++;
            $this->renderContactDetail($contact, $driver, $count);
        }

        if ($count === 0) {
            echo '<p class="horde-content"><em>' . $this->escapeHtml(_("No contacts found.")) . '</em></p>';
        } else {
            echo '<p class="horde-content">'
                . sprintf($this->escapeHtml(_("Displayed %d contact(s).")), $count)
                . '</p>';
        }
    }

    private function renderContactDetail(Turba_Object $contact, Turba_Driver $driver, int $index): void
    {
        $name = $contact->getValue('name');
        $key = $contact->getValue('__key');

        echo '<h2 class="smallheader">'
            . $this->escapeHtml(sprintf(_("Contact #%d"), $index)) . ': '
            . $this->escapeHtml(is_string($name) ? $name : var_export($name, true))
            . ' <small>(__key: <code>' . $this->escapeHtml(is_string($key) ? $key : var_export($key, true)) . '</code>)</small>'
            . '</h2>';

        // Table A: Raw attributes
        echo '<h3>' . $this->escapeHtml(_("Raw Attributes")) . ' <small>($contact-&gt;attributes)</small></h3>';
        echo '<table class="horde-table">';
        echo '<thead><tr>';
        echo '<th>' . $this->escapeHtml(_("Turba Key")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Backend Field")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Raw Value")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("PHP Type")) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        $rawAttrs = $contact->attributes;
        ksort($rawAttrs);
        $map = $driver->map;

        foreach ($rawAttrs as $turbaKey => $rawValue) {
            $backendField = isset($map[$turbaKey]) && !is_array($map[$turbaKey])
                ? $map[$turbaKey]
                : '—';

            echo '<tr>';
            echo '<td><code>' . $this->escapeHtml($turbaKey) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($backendField) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($this->formatValue($rawValue)) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($this->describeType($rawValue)) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Table B: getValue() output
        echo '<h3>' . $this->escapeHtml(_("getValue() Output")) . ' <small>(' . $this->escapeHtml(_("what the display layer receives")) . ')</small></h3>';
        echo '<table class="horde-table">';
        echo '<thead><tr>';
        echo '<th>' . $this->escapeHtml(_("Turba Field")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("getValue() Result")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("PHP Type")) . '</th>';
        echo '<th>' . $this->escapeHtml(_("Differs from raw?")) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        $allKeys = array_keys($driver->getCriteria());
        sort($allKeys);

        foreach ($allKeys as $fieldKey) {
            $getValue = $contact->getValue($fieldKey);
            $rawValue = $rawAttrs[$fieldKey] ?? null;

            // Detect differences
            $differs = ($getValue !== $rawValue);
            $diffClass = $differs ? ' style="background-color: #fff3cd;"' : '';

            echo '<tr' . $diffClass . '>';
            echo '<td><code>' . $this->escapeHtml($fieldKey) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($this->formatValue($getValue)) . '</code></td>';
            echo '<td><code>' . $this->escapeHtml($this->describeType($getValue)) . '</code></td>';
            echo '<td>' . ($differs ? '<strong>YES</strong>' : 'no') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $exported = var_export($value, true);
            // Check for binary data in arrays
            if (isset($value['load']['data']) && !mb_check_encoding((string) $value['load']['data'], 'UTF-8')) {
                return '[image data, ' . strlen((string) $value['load']['data']) . ' bytes]';
            }
            return $this->truncate($exported, 200);
        }

        if (is_string($value)) {
            // Check for binary data
            if (!mb_check_encoding($value, 'UTF-8')) {
                return '[binary, ' . strlen($value) . ' bytes]';
            }
            return $this->truncate($value, 200);
        }

        return $this->truncate(var_export($value, true), 200);
    }

    private function describeType(mixed $value): string
    {
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }

        if (is_string($value)) {
            return 'string(' . strlen($value) . ')';
        }

        return gettype($value);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength) . '...';
    }
}
