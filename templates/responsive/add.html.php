<main class="container turba-container">
    <div class="page-header">
        <a href="<?php echo htmlspecialchars($this->backUrl) ?>" class="back-link">
            <span class="back-arrow">‹</span>
            <?php echo _("Back to Contacts") ?>
        </a>
        <h1><?php echo _("Add Contact") ?></h1>
    </div>

    <?php if (!$this->hasWritableSources): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📇</div>
            <h2><?php echo _("No Writable Address Books") ?></h2>
            <p><?php echo _("You don't have permission to add contacts to any address book.") ?></p>
        </div>
    <?php else: ?>
        <div class="contact-form-container">
            <form method="post" action="<?php echo htmlspecialchars(Horde::url('responsive/add', true)) ?>" class="contact-form">
                <div class="form-group">
                    <label for="source" class="form-label">
                        <?php echo _("Address Book") ?> <span class="required">*</span>
                    </label>
                    <select name="source" id="source" class="form-control" required>
                        <option value=""><?php echo _("Select address book...") ?></option>
                        <?php foreach ($this->writableSources as $key => $title): ?>
                            <option value="<?php echo htmlspecialchars($key) ?>">
                                <?php echo htmlspecialchars($title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="name" class="form-label">
                        <?php echo _("Name") ?> <span class="required">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           class="form-control"
                           placeholder="<?php echo _("Full name") ?>"
                           required
                           autocomplete="name">
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        <?php echo _("Email") ?>
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control"
                           placeholder="<?php echo _("email@example.com") ?>"
                           autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">
                        <?php echo _("Mobile Phone") ?>
                    </label>
                    <input type="tel"
                           name="phone"
                           id="phone"
                           class="form-control"
                           placeholder="<?php echo _("+1 234 567 8900") ?>"
                           autocomplete="tel">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo _("Add Contact") ?>
                    </button>
                    <a href="<?php echo htmlspecialchars($this->backUrl) ?>" class="btn btn-secondary">
                        <?php echo _("Cancel") ?>
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</main>
