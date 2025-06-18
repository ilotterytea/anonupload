let options = JSON.parse(localStorage.getItem('options') ?? '{}');

document.querySelectorAll('input[type=checkbox]').forEach((c) => {
    const id = c.getAttribute('name');

    c.addEventListener('change', () => {
        options[id] = c.checked;
        localStorage.setItem('options', JSON.stringify(options));
    });

    if (options[id] !== undefined) {
        c.checked = options[id];
    }
});

document.querySelectorAll('select').forEach((c) => {
    const id = c.getAttribute('name');

    c.addEventListener('change', () => {
        options[id] = c.value;
        localStorage.setItem('options', JSON.stringify(options));
    });

    if (options[id] !== undefined) {
        c.value = options[id];
    }
});