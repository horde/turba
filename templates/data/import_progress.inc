<h1 class="header">
 <?php echo _("Importing Contacts") ?>
</h1>

<div class="horde-content turba-import-progress">
 <p id="turba-import-progress-text"><?php echo htmlspecialchars(sprintf(_("Importing contacts: %d / %d (%d%%)"), 0, (int) $import_total, 0)) ?></p>
 <progress id="turba-import-progress-bar" max="<?php echo (int) $import_total ?>" value="0"></progress>
 <p id="turba-import-progress-summary" class="turba-import-progress-summary" style="display:none;"></p>
 <p id="turba-import-progress-error" class="turba-import-progress-error" style="display:none;"></p>
 <p id="turba-import-progress-done" style="display:none;">
  <a href="<?php echo Horde::url('data.php') ?>"><?php echo _("Return to Import/Export") ?></a>
 </p>
</div>
