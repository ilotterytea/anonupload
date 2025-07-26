function displayTab(category, id) {
    const tabs = document.querySelectorAll(`#${category} .tab`);
    tabs.forEach((tab) => {
        let tabId = tab.getAttribute('id');
        tabId = tabId.substring(0, tabId.length - 4);

        tab.setAttribute('show-disabled', tabId != id);

        const content = document.getElementById(tabId);
        if (content) {
            let display = 'flex';
            if (content.classList.contains('grid')) {
                display = 'grid';
            }
            content.style.display = tabId == id ? display : 'none';
        }
    });
}

function enableTab(id) {
    document.getElementById(`${id}-tab`).style.display = 'flex';
}

function disableTab(id) {
    document.getElementById(`${id}-tab`).style.display = 'none';
}

function hideTab(id) {
    disableTab(id);
    document.getElementById(id).style.display = 'none';
}

function showTab(id) {
    enableTab(id);
    const content = document.getElementById(id);
    if (content) {
        content.style.display = content.classList.contains('grid') ? 'grid' : 'flex';
    }
}

window.addEventListener('load', () => {
    const categories = document.querySelectorAll('.tab-category');

    categories.forEach((c) => {
        const category = c.getAttribute('id');
        const tabs = document.querySelectorAll(`#${category} .tab>button`);
        tabs.forEach((tab) => {
            let id = tab.parentElement.getAttribute('id');
            id = id.substring(0, id.length - 4);

            tab.addEventListener('click', () => displayTab(category, id));
        });
    });
});