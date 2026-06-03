function initOptions(rootId) {
    let options = JSON.parse(localStorage.getItem('options') ?? '{}');

    document.querySelectorAll(`#${rootId} input`).forEach((c) => {
        const id = c.getAttribute("name");

        switch (c.getAttribute("type")) {
            case "text": {
                c.addEventListener("change", () => {
                    options[id] = c.value;
                    localStorage.setItem("options", JSON.stringify(options));
                });
                if (options[id] !== undefined) {
                    c.value = options[id];
                }
                break;
            }
            case "checkbox": {
                c.addEventListener("change", () => {
                    options[id] = c.checked;
                    localStorage.setItem("options", JSON.stringify(options));
                });
                if (options[id] !== undefined) {
                    c.checked = options[id];
                }
                break;
            }
            default: break;
        }
    });

    document.querySelectorAll(`#${rootId} select`).forEach((c) => {
        const id = c.getAttribute('name');

        c.addEventListener('change', () => {
            options[id] = c.value;
            localStorage.setItem('options', JSON.stringify(options));
        });

        if (options[id] !== undefined) {
            c.value = options[id];
            c.dispatchEvent(new Event("change", { bubbles: true }));
        }
    });
}