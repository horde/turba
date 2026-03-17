<?php

if ($ob->hasValue('name')) {
    $link_text = htmlspecialchars(Turba::formatName($ob));
} elseif (isset($ob->driver->alternativeName) &&
          !in_array($ob->driver->alternativeName, $this->columns) &&
          $ob->hasValue($ob->driver->alternativeName)) {
    $link_text = htmlspecialchars($ob->getValue($ob->driver->alternativeName));
} else {
    $link_text = '<em>' . _("Blank name") . '</em>';
}

if ($own_source == $ob->getSource() && $own_id == $ob->getValue('__key')) {
    $own_icon = Horde_Themes_Image::tag('user.png', array(
        'alt' => _("Your own contact"),
        'attr' => array('title' => _("Your own contact"))
    ));
} else {
    $own_icon = '&nbsp;';
}

$edit_url = $vcard_url = '&nbsp;';
if ($ob->hasValue('__key')) {
    if (!$ob->isGroup()) {
        $vcard_url = Horde::link(Horde::url('vcard.php')->add(array(
            'source' => $ob->getSource(),
            'key' => $ob->getValue('__key')
        ), _("Download vCard"))) . '<span class="iconImg vcardImg"></span></a>';
    }

    $cell = Horde::link($ob->url()) . $link_text . '</a>';

    if ($ob->hasPermission(Horde_Perms::EDIT)) {
        $edit_url = Horde::link(Horde::url('edit.php')->add(array(
            'key' => $ob->getValue('__key'),
            'source' => $ob->getSource(),
            'url' => Horde::signUrl(Horde::selfUrl(true, false, true))
        ), _("Edit"))) . '<span class="iconImg editImg"></span></a>';
    }
} else {
    $cell = $link_text;
}

?>
<tr>
<?php if ($this->showMark): ?>
  <td class="turba-browse-icon"><label for="<?php echo htmlspecialchars($ob->getSource() . ':' . $ob->getValue('__key')) ?>" class="hidden"><?php echo _("Select contact") ?></label><input type="checkbox" class="checkbox" name="objectkeys[]" id="<?php echo htmlspecialchars($ob->getSource() . ':' . $ob->getValue('__key')) ?>" value="<?php echo htmlspecialchars($ob->getSource() . ':' . $ob->getValue('__key')) ?>" /></td>
<?php endif; ?>
<?php if ($this->showEdit): ?>
  <td class="turba-browse-icon"><?php echo $edit_url ?></td>
<?php endif; ?>
<?php if ($this->showVcard): ?>
  <td class="turba-browse-icon"><?php echo $vcard_url ?></td>
<?php endif; ?>
<?php if ($this->showGroup): ?>
  <td class="turba-browse-icon"><?php echo $ob->isGroup() ? '<span class="iconImg groupImg" title="' . htmlspecialchars(_("Contact List")) . '"></span>' : $own_icon ?></td>
<?php endif; ?>
  <td<?php if ($this->showSort && $this->isSortColumn(0)) echo ' class="linedRowSelectedCol"' ?>><?php
echo $cell;

// TODO: The comment is wrong/irrelevant
// We purposefully do this before the </td> so that if we end up including a
// script file (say, for the IMP compose window), it's in a legal spot.
//
// Build the columns to display.
$shown_columns = [];
$c = 0;
foreach ($this->columns as $column) {
    $type = $GLOBALS['attributes'][$column]['type'];
    if ($ob->hasValue($column)) {
        $value = $ob->getValue($column);
        if ($type == 'email') {
            $value = Turba::formatEmailAddresses($value, $ob->getValue('name'));
        }
        $this->vars->set($column, $value);
        $value = $this->renderer->render(null, $this->variables[$c], $this->vars);
    } else {
        $value = '&nbsp;';
    }
    $shown_columns[] = $value;
    ++$c;
}

echo '</td>';
// start index with 1 as this is what isSortColumns() expects
$c = 1;
foreach ($shown_columns as $value) {
    echo '<td';
    if ($this->showSort && $this->isSortColumn($c)) {
        echo ' class="linedRowSelectedCol"';
    }
    echo '>' . $value . '</td>';
    ++$c;
}
?>
</tr>
