<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($this->contact['name']) ?> - <?php echo _("Contacts") ?> - Horde</title>

    <!-- Responsive styles (cascade: horde base + turba app) -->
    <?php foreach ($this->cssUrls as $url): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($url) ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php echo $this->topbar ?>

    <main class="container turba-container">
        <div class="page-header">
            <a href="<?php echo htmlspecialchars($this->backUrl) ?>" class="back-link">
                <span class="back-arrow">‹</span>
                <?php echo _("Back to Contacts") ?>
            </a>
        </div>

        <div class="contact-detail">
            <?php if ($this->contact['photo']): ?>
                <div class="contact-photo-container">
                    <img src="<?php echo htmlspecialchars($this->contact['photo']) ?>"
                         alt="<?php echo htmlspecialchars($this->contact['name']) ?>"
                         class="contact-photo">
                </div>
            <?php endif; ?>

            <h1 class="contact-name-header">
                <?php echo htmlspecialchars($this->contact['name']) ?>
                <?php if ($this->contact['isGroup']): ?>
                    <span class="contact-group-badge"><?php echo _("Contact List") ?></span>
                <?php endif; ?>
            </h1>

            <?php if (!empty($this->contact['quickActions'])): ?>
                <div class="quick-actions">
                    <?php foreach ($this->contact['quickActions'] as $action): ?>
                        <a href="<?php echo htmlspecialchars($action['url']) ?>"
                           class="quick-action-btn quick-action-<?php echo htmlspecialchars($action['type']) ?>"
                           <?php if ($action['type'] === 'map'): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                            <span class="quick-action-icon"><?php echo $action['icon'] ?></span>
                            <span class="quick-action-label"><?php echo htmlspecialchars($action['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($this->contact['sections'])): ?>
                <?php foreach ($this->contact['sections'] as $sectionName => $fields): ?>
                    <div class="contact-section">
                        <details class="section-collapsible" open>
                            <summary class="section-header">
                                <?php echo htmlspecialchars($sectionName) ?>
                            </summary>

                            <div class="section-content">
                                <?php foreach ($fields as $field): ?>
                                    <div class="contact-field">
                                        <div class="field-label">
                                            <?php echo htmlspecialchars($field['label']) ?>
                                        </div>
                                        <div class="field-value">
                                            <?php if ($field['link']): ?>
                                                <a href="<?php echo htmlspecialchars($field['link']) ?>"
                                                   class="field-link field-link-<?php echo htmlspecialchars($field['type']) ?>">
                                                    <?php
                                                    switch ($field['type']) {
                                                        case 'email':
                                                            echo '✉️ ';
                                                            break;
                                                        case 'phone':
                                                            echo '📞 ';
                                                            break;
                                                        case 'address':
                                                            echo '📍 ';
                                                            break;
                                                    }
                                                ?>
                                                    <?php echo htmlspecialchars($field['value']) ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($field['value']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($this->contact['groupMembers'])): ?>
                <div class="contact-section">
                    <details class="section-collapsible" open>
                        <summary class="section-header">
                            <?php echo _("Contact List Members") ?>
                            <span class="member-count"><?php echo count($this->contact['groupMembers']) ?></span>
                        </summary>

                        <div class="section-content">
                            <ul class="member-list">
                                <?php foreach ($this->contact['groupMembers'] as $member): ?>
                                    <li class="member-item">
                                        <a href="<?php echo htmlspecialchars($member['url']) ?>"
                                           class="member-link">
                                            <span class="member-icon">👤</span>
                                            <span class="member-name"><?php echo htmlspecialchars($member['name']) ?></span>
                                            <span class="member-chevron">›</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

            <?php if (empty($this->contact['sections']) && empty($this->contact['groupMembers'])): ?>
                <div class="empty-state">
                    <p><?php echo _("No contact information available.") ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Responsive JavaScript -->
    <?php foreach ($this->jsUrls as $url): ?>
    <script src="<?php echo htmlspecialchars($url) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
