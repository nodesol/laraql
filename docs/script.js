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
            }
        } catch (error) {
            console.error("Error loading components:", error);
        }
    })

    initMobileMenu();
    initSearch();
}

/**
 * 2. MOBILE HAMBURGER LOGIC
 * Handles opening/closing the sidebar on small screens.
 */
function initMobileMenu() {
    document.addEventListener('click', (e) => {
        const menuBtn = e.target.closest('#mobile-menu-button');
        const sidebar = document.getElementById('sidebar');

        if (menuBtn && sidebar) {
            e.preventDefault();
            // Using 'mobile-active' as your custom overlay class
            sidebar.classList.toggle('mobile-active');
            document.body.classList.toggle('overflow-hidden');

            // Swap Icon
            const icon = menuBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-xmark');
            }
        }
    });
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

function toggleSearch(forceClose = false) {
    const modal = document.getElementById('search-modal');
    const input = document.getElementById('search-input');
    
    if (!modal) return;

    if (forceClose) {
        modal.classList.add('hidden');
    } else {
        modal.classList.toggle('hidden');
        if (!modal.classList.contains('hidden')) {
            // Tiny delay to ensure modal is visible before focusing
            setTimeout(() => input?.focus(), 10);
        }
    }
}

/**
 * 4. SEARCH MODAL LOGIC
 * Handles Cmd+K / Ctrl+K and the visual trigger.
 */
// function initSearch() {
//     // Use delegation for the trigger
//     document.addEventListener('click', (e) => {
//         // This finds the trigger even if it was added via fetch()
//         if (e.target.closest('#search-trigger')) {
//             toggleSearch();
//         }
//     });

//     document.addEventListener('keydown', (e) => {
//         // Prevent shortcut if user is already typing in a different input
//         const isTyping = ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName);
        
//         if ((e.metaKey || e.ctrlKey) && e.key === 'k' && !isTyping) {
//             e.preventDefault();
//             toggleSearch();
//         }
//         if (e.key === 'Escape') toggleSearch(true);
//     });
// }

let searchData = [];

async function initSearch() {
    const modal = document.getElementById('search-modal');
    const input = document.getElementById('search-input');
    const resultsContainer = document.getElementById('search-results');

    try {
        const response = await fetch('search-index.json');
        searchData = await response.json();
    } catch (e) { console.error("Search index failed to load"); }

    // Resetting listeners using Event Delegation on 'document' 
    // ensures it works even if content is re-injected via fetch()
    document.addEventListener('click', (e) => {
        // Fix: Click on Search Trigger
        if (e.target.closest('#search-trigger')) {
            toggleSearch();
        }
    });

    // Keyboard Shortcut Fix
    document.addEventListener('keydown', (e) => {
        const isTyping = ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName);
        
        // Open on Cmd+K or Ctrl+K
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            if (!isTyping) {
                e.preventDefault(); // CRITICAL: Stop browser from taking the shortcut
                toggleSearch();
            }
        }
        
        // Close on ESC
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            toggleSearch(true);
        }
    });

    // Result Logic (Input Listener)
    input?.addEventListener('input', async (e) => {
        // console.log(e.target.value.toLowerCase())
        const query = e.target.value.toLowerCase();
        console.log(query.length)
        
        // Ensure this doesn't block the rest of the script
        if (query.length < 2) {
            resultsContainer.innerHTML = '<p class="text-sm text-slate-500 italic">Type at least 2 characters...</p>';
            return;
        }

        const matches = searchData.filter(item => 
            item.title.toLowerCase().includes(query) || 
            item.text.toLowerCase().includes(query)
        );

        if (matches.length === 0) {
            resultsContainer.innerHTML = '<p class="text-sm text-slate-500">No results found.</p>';
            return;
        }
        resultsContainer.innerHTML = '';

        // Render matching results
        matches.forEach(match => {
            const div = document.createElement('div');
            div.className = "mb-4 p-3 hover:bg-slate-50 rounded-lg border border-transparent hover:border-slate-200 transition cursor-pointer";
            div.innerHTML = `
                <a href="${match.url}" class="block">
                    <div class="text-indigo-600 font-bold text-sm">${match.title}</div>
                    <div class="text-slate-500 text-xs line-clamp-1">${match.text}</div>
                </a>
            `;
            resultsContainer.appendChild(div);
        });
    });
}