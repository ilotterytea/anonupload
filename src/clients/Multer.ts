// Copyright 2022 NotDankEnough (ilotterytea)
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

import multer from "multer";
import mime from "mime";
import crypto from "crypto";
import { readdirSync } from "fs";

const fileFilter = (req: Express.Request, res: Express.Response, cb: multer.FileFilterCallback) => {
    cb(null, true);
}

const storage = multer.diskStorage({
    destination: "static/images",
    filename(req, file, callback) {
        if (!req.headers["pattern"]) req.headers["pattern"] = "random";

        switch (req.headers["pattern"]) {
            case "numeration": {
                const img_id: number = readdirSync("images").length + 1;
                const max_zero: number = 5;
                var blank_string: string = "";

                for (var i = 0; i < max_zero; i++) { blank_string = blank_string + "0"; }

                blank_string = blank_string.slice(img_id.toString().length, blank_string.length);

                callback(null, `${blank_string}${img_id.toString()}.${mime.getExtension(file.mimetype)}`);
                break;
            }
            case "random": {
                crypto.randomBytes(5, (err, raw) => {
                    if (err) return callback(err, "");
                    callback(null, `${raw.toString("hex")}.${mime.getExtension(file.mimetype)}`)
                });
                break;
            }
            default: {
                crypto.randomBytes(5, (err, raw) => {
                    if (err) return callback(err, "");
                    callback(null, `${raw.toString("hex")}.${mime.getExtension(file.mimetype)}`)
                });
                break;
            }
        }
    }
});

const Multer = multer({
    fileFilter: fileFilter,
    storage: storage,
    limits: {
        fieldSize: 1.6e+7
    }
});

export default Multer;