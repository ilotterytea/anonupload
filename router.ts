// Copyright 2022 ilotterytea
// 
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// 
//     http://www.apache.org/licenses/LICENSE-2.0
// 
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

import express, {
    RequestHandler
} from "express";
import favicon from "serve-favicon";
import pck from "./package.json";
import i_opts from "./options.json";
import multer from "multer";
import crypto from "crypto";

import { existsSync, readdirSync, readFileSync } from "fs";
import mime from "mime";

const router = express.Router();

// To store files on local disk:
const storage = multer.diskStorage({
    // File destination:
    destination: "./static/i",
    // Creation of file name:
    filename: (req, file, callback) => {
        // Compare file size and limit:
        if (file.size > i_opts.maxFileSizeInBytes) {
            callback(Error("The file size is larger than the maximum set value."), "");
            return;
        }

        // Name pattern:
        var pattern = "number/dir";

        switch (pattern) {
            // A name for the file based on where it will be positioned amongst the other files after uploading:
            case "number/dir":
                const img_id = readdirSync("./static/i").length + 1;
            
                var maxzero = 5;
                var zerostring = "";
                
                for (var i = 0; i < maxzero; i++) { zerostring = zerostring + "0"; }

                zerostring = zerostring.slice(img_id.toString().length, zerostring.length);

                callback(null, zerostring + img_id.toString() + "." + mime.extension(file.mimetype));
                break;
            // Random byte name:
            case "crypto/hex":
                crypto.randomBytes(5 /* <-- Character length */, (err, raw) => {
                    if (err) return callback(err, "");
                    callback(null, raw.toString("hex") + "." + mime.extension(file.mimetype));
                });
                break;
            default:
                break;
        }
    }
});

// Image uploader:
const upload = multer({
    storage: storage,
    fileFilter(req, file, callback) {
        if (i_opts.allowedMIMETypes.includes(file.mimetype)) {
            callback(null, true);
        } else {
            callback(null, false);
            console.debug("The extension of this file isn't allowed.");
        }
    },
})

// Main page:
router.get("/", async (req, res) => {
    var Page: string = readFileSync(`${__dirname}/static/html/index.html`, {encoding: "utf-8"});

    Page = Page.replace("{{AboutFileHoster}}", `Running on notdankenough/fh@${pck.version}!`);

    res.send(Page);
});

// Viewing files.
router.get("/i/:id", async (req, res) => {
    if (!(existsSync("./static/i/" + req.params.id))) {
        return res.json({
            status: 404,
            reason: "The file " + req.params.id + " not found!",
            kitty: "https://http.cat/404"
        }).status(404);
    }

    return res.sendFile(`${__dirname}/static/i/${req.params.id}`);
});

// POST request to upload a file and then send its name:
router.post("/dank_upload", upload.single("file"), (req, res) => {
    var name = req.file?.filename;

    // Required for some external tools:
    if (req.headers["no-redirect"]) {
        return res.json({
            name: name
        }).status(200);
    }

    res.redirect("/i/" + name);
});

export default router;