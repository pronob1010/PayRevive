document.addEventListener('DOMContentLoaded', function() {
    // Tabs functionality
    const tabLinks = document.querySelectorAll('.payrevive-tab-link');
    const tabContents = document.querySelectorAll('.payrevive-tab-content');

    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('data-tab');

            // Update Tab Links
            tabLinks.forEach(l => {
                l.classList.remove('active');
            });
            this.classList.add('active');

            // Update Tab Content
            tabContents.forEach(c => {
                c.classList.add('hidden');
                c.classList.remove('block');
            });
            const activeContent = document.getElementById(target);
            if (activeContent) {
                activeContent.classList.remove('hidden');
                activeContent.classList.add('block');
            }
            
            // Save active tab to local storage
            localStorage.setItem('payrevive_active_tab', target);
        });
    });

    // Restore active tab
    const activeTabId = localStorage.getItem('payrevive_active_tab');
    if (activeTabId) {
        const activeLink = document.querySelector(`.payrevive-tab-link[data-tab="${activeTabId}"]`);
        if (activeLink) {
            // Trigger click without dispatchEvent to reuse the logic above
            // But we need to make sure the initial state matches what click does
            activeLink.click();
        }
    }
});
