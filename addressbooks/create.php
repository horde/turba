<?php
/**
 * Turba addressbooks - create.
 *
 * Copyright 2001-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('turba');

// Exit if this isn't an authenticated user, or if there's no source
// configured for shares.
if (!$GLOBALS['registry']->getAuth() || !$session->get('turba', 'has_share')) {
    Horde::url('', true)->redirect();
}

$vars = Horde_Variables::getDefaultVariables();
$form = new Turba_Form_CreateAddressBook($vars);

// Execute if the form is valid.
if ($form->validate($vars)) {
    try {
        $result = $form->execute();
        $notification->push(sprintf(_("The address book \"%s\" has been created."), $vars->get('name')), 'horde.success');
        Horde::url('addressbooks/edit.php')
            ->add('a', $result->getName())
            ->redirect();
    } catch (Turba_Exception $e) {
        $notification->push($e);
    }
}

$page_output->header(array(
    'title' => $form->getTitle()
));
$notification->notify(array('listeners' => 'status'));
echo $form->renderActive($form->getRenderer(), $vars, Horde::url('addressbooks/create.php'), 'post');
$page_output->footer();
