function isSyncAvailable() {
    return crypto.subtle !== undefined;
}

async function hashSyncCode(code) {
    const data = new TextEncoder().encode(code);
    const hash = await crypto.subtle.digest("SHA-256", data);

    return [...new Uint8Array(hash)]
        .map(x => x.toString(16).padStart(2, "0"))
        .join("");
}

async function deriveKey(code, salt) {
    const password = new TextEncoder().encode(code);

    const keyMaterial = await crypto.subtle.importKey(
        "raw",
        password,
        "PBKDF2",
        false,
        ["deriveKey"]
    );

    return crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt,
            iterations: 600000,
            hash: "SHA-256"
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt", "decrypt"]
    );
}

async function encryptJson(json, code) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));

    const key = await deriveKey(code, salt);
    const text = new TextEncoder().encode(JSON.stringify(json));
    const data = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, text);

    return {
        salt: btoa(String.fromCharCode(...salt)),
        iv: btoa(String.fromCharCode(...iv)),
        data: btoa(String.fromCharCode(... new Uint8Array(data)))
    };
}

function btou(b64) {
    const str = atob(b64);
    const len = str.length;
    const b = new Uint8Array(len);

    for (let i = 0; i < len; i++) {
        b[i] = str.charCodeAt(i);
    }

    return b;
}

async function decryptJson(blob, code) {
    const salt = btou(blob.salt);
    const iv = btou(blob.iv);

    const key = await deriveKey(code, salt);

    const text = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, key, btou(blob.data));

    return JSON.parse(
        new TextDecoder().decode(text)
    );
}