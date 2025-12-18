/**
 * 1. COMPONENT LOADER
 * Fetches nav.html and footer.html and injects them into the page.
 */
async function loadComponents(path, components) {
    components.forEach(async (component)=>{
        try {
        // Load Navbar/Sidebar
            const response = await fetch(`${path}includes/${component}.html`);
            if (response.ok) {
                document.getElementById(`${component}-placeholder`).innerHTML = await response.text();
                // Initialize interactions ONLY after HTML is injected
                initMobileMenu();
                initSearch();
            }
        } catch (error) {
            console.error("Error loading components:", error);
        }
    })
}

/**
 * 2. MOBILE HAMBURGER LOGIC
 * Handles opening/closing the sidebar on small screens.
 */
function initMobileMenu() {
    const menuBtn = document.getElementById('mobile-menu-button');
    const sidebar = document.getElementById('sidebar');

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => {
            // Toggle our custom mobile class
            sidebar.classList.toggle('mobile-active');
            
            // Toggle overflow on body to prevent background scrolling when menu is open
            document.body.classList.toggle('overflow-hidden');

            // Swap Icon
            const icon = menuBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-xmark');
            }
        });
    }
}

/**
 * 3. SUB-MENU TOGGLE (ATTRIBUTES)
 * This is global so it can be called by the 'onclick' attributes in nav.html
 */
function toggleSubMenu(menuId, chevronId) {
    const menu = document.getElementById(menuId);
    const chevron = document.getElementById(chevronId);
    
    if (menu) menu.classList.toggle('hidden');
    if (chevron) chevron.classList.toggle('rotate-180');
}

/**
 * 4. SEARCH MODAL LOGIC
 * Handles Cmd+K / Ctrl+K and the visual trigger.
 */
function initSearch() {
    const modal = document.getElementById('search-modal');
    const trigger = document.getElementById('search-trigger');
    const input = document.getElementById('search-input');

    const openSearch = () => {
        modal?.classList.remove('hidden');
        input?.focus();
    };

    const closeSearch = () => {
        modal?.classList.add('hidden');
    };

    // Click trigger
    trigger?.addEventListener('click', openSearch);

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
        // Open on Cmd+K or Ctrl+K
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
        // Close on ESC
        if (e.key === 'Escape') {
            closeSearch();
        }
    });
}