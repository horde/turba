/**
 * Turba Responsive JavaScript
 * Vanilla JS for contact filtering and interactions
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 */

(function() {
    'use strict';

    /**
     * Initialize contact filtering
     */
    function initContactFilter() {
        const searchInput = document.getElementById('contact-search');
        const clearButton = document.getElementById('search-clear');
        const noResults = document.getElementById('no-results');

        if (!searchInput) {
            return;
        }

        // Handle search input
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();

            // Show/hide clear button
            if (clearButton) {
                clearButton.hidden = query.length === 0;
            }

            filterContacts(query);
        });

        // Handle clear button
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                clearButton.hidden = true;
                filterContacts('');
                searchInput.focus();
            });
        }

        /**
         * Filter contacts by search query
         *
         * @param {string} query Search query
         */
        function filterContacts(query) {
            const contactSources = document.querySelectorAll('.contact-source');
            let totalVisible = 0;
            let anySourceVisible = false;

            contactSources.forEach(function(source) {
                const items = source.querySelectorAll('.contact-item');
                let visibleInSource = 0;

                items.forEach(function(item) {
                    const name = item.getAttribute('data-name') || '';
                    const email = item.getAttribute('data-email') || '';

                    // Check if query matches name or email
                    const matches = name.includes(query) || email.includes(query);

                    item.hidden = !matches;

                    if (matches) {
                        visibleInSource++;
                        totalVisible++;
                    }
                });

                // Hide entire source if no contacts match
                source.hidden = visibleInSource === 0 && query.length > 0;

                if (visibleInSource > 0) {
                    anySourceVisible = true;
                }

                // Update count badge
                const countBadge = source.querySelector('.source-count');
                if (countBadge) {
                    countBadge.textContent = visibleInSource;
                }
            });

            // Show/hide "no results" message
            if (noResults) {
                noResults.hidden = anySourceVisible || query.length === 0;
            }
        }
    }

    /**
     * Initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initContactFilter);
    } else {
        initContactFilter();
    }

})();
