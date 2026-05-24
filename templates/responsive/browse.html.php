<main class="container turba-container">
    <div class="page-header">
        <h1><?php echo _("Contacts") ?></h1>
        <a href="<?php echo htmlspecialchars(Horde::url('responsive/add', true)) ?>" class="add-contact-btn">
            <span class="add-contact-icon">+</span>
            <?php echo _("Add Contact") ?>
        </a>
    </div>

    <?php if (!$this->hasContacts): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📇</div>
            <h2><?php echo _("No Address Books") ?></h2>
            <p><?php echo _("There are no browseable address books configured.") ?></p>
        </div>
    <?php else: ?>
        <div class="search-widget">
            <div class="search-input-wrapper">
                <input type="search"
                       id="contact-search"
                       class="search-input"
                       placeholder="<?php echo _("Search contacts...") ?>"
                       autocomplete="off">
                <button type="button"
                        class="btn-clear"
                        id="search-clear"
                        aria-label="<?php echo _("Clear search") ?>"
                        hidden>
                    ×
                </button>
            </div>
        </div>

        <div id="contact-sources">
            <?php foreach ($this->contactList as $sourceKey => $sourceData): ?>
                <div class="contact-source" data-source="<?php echo htmlspecialchars($sourceKey) ?>">
                    <details class="source-collapsible" open>
                        <summary class="source-header">
                            <span class="source-title"><?php echo htmlspecialchars($sourceData['title']) ?></span>
                            <span class="source-count"><?php echo count($sourceData['contacts']) ?></span>
                        </summary>

                        <?php if (empty($sourceData['contacts'])): ?>
                            <div class="source-empty-message">
                                <p><?php echo _("This address book is empty.") ?></p>
                            </div>
                        <?php else: ?>
                            <ul class="contact-list">
                                <?php foreach ($sourceData['contacts'] as $contact): ?>
                                    <li class="contact-item"
                                        data-name="<?php echo htmlspecialchars(strtolower($contact['name'])) ?>"
                                        data-email="<?php echo htmlspecialchars(strtolower($contact['email'])) ?>">
                                        <a href="<?php echo htmlspecialchars(Horde::url('responsive/contact/' . $sourceKey . '/' . $contact['key'], true)) ?>"
                                           class="contact-link">
                                            <span class="contact-icon">
                                                <?php if ($contact['isGroup']): ?>
                                                    <img src="<?php echo htmlspecialchars($this->groupIconUrl) ?>"
                                                         alt="<?php echo _("Contact List") ?>"
                                                         class="contact-group-icon">
                                                <?php else: ?>
                                                    👤
                                                <?php endif; ?>
                                            </span>
                                            <span class="contact-info">
                                                <span class="contact-name"><?php echo htmlspecialchars($contact['name']) ?></span>
                                                <?php if ($contact['email']): ?>
                                                    <span class="contact-email"><?php echo htmlspecialchars($contact['email']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="contact-chevron">›</span>
                                        </a>
                                        <?php if ($contact['phone'] && !$contact['isGroup']): ?>
                                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^\d+]/', '', $contact['phone'])) ?>"
                                               class="contact-call-btn"
                                               title="<?php echo _("Call") ?>"
                                               onclick="event.stopPropagation();">
                                                📞
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="empty-state-filtered" id="no-results" hidden>
            <p><?php echo _("No contacts match your search.") ?></p>
        </div>
    <?php endif; ?>
</main>
