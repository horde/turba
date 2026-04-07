<input type="hidden" name="columns" id="columns" value="<?php echo $this->columns ?>" />

<?php if (!empty($this->col_list)): ?>

<div id="turba-prefs-cols-container">
 <p>
  <?php echo _("Click an address book to sort its columns. Drag columns to re-arrange them. Check a column to enable it.") ?>
 </p>

 <div id="turba-prefs-cols-list">
  <ul>
<?php foreach ($this->col_list as $col): ?>
   <li<?php if ($col['first']): ?> class="active"<?php endif ?>><a href="#" sourcename="<?php echo $col['source'] ?>"><?php echo $col['title'] ?></a></li>
<?php endforeach ?>
  </ul>
 </div>

 <div id="turba-prefs-cols-columns">
<?php foreach ($this->cols as $col): ?>
  <div class="turba-prefs-cols-panel" id="turba-prefs-cols-panel-<?php echo $col['source'] ?>" style="display:<?php echo $col['first'] ? 'block' : 'none' ?>;">
   <ol id="turba-prefs-<?php echo $col['source'] ?>">
<?php foreach ($col['inputs'] as $input): ?>
    <li id="turba-prefs-cols-<?php echo $col['source'] ?>_<?php echo $input['i'] ?>">
     <input id="turba-prefs-cols-<?php echo $col['source'] ?>-<?php echo $input['column'] ?>" type="checkbox" class="checkbox"<?php if ($input['checked']): ?> checked="checked"<?php endif ?> /><?php echo $input['label'] ?>
    </li>
<?php endforeach ?>
   </ol>
  </div>
<?php endforeach ?>
 </div>

 <br class="clear" />

</div>
<?php endif ?>
