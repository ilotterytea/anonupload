let options = JSON.parse(localStorage.getItem('options') ?? '{}');

const checkboxes = document.querySelectorAll('input[type=checkbox]');

checkboxes.forEach((c) => {
    const id = c.getAttribute('name');

    c.addEventListener('change', () => {
        options[id] = c.checked;
        localStorage.setItem('options', JSON.stringify(options));
    });

    if (options[id] !== undefined) {
        c.checked = options[id];
    }
});