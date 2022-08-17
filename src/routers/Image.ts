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

import { PrismaClient, User, Image } from "@prisma/client";
import express from "express";
import mime from "mime";
import multer from "../clients/Multer";

function ImageRouter(dirPath: string, cfg: {[key: string]: any}, prisma: PrismaClient): express.Router {
    const router: express.Router = express.Router();

    router.get("/", async (req, res) => {
        const user: User | null = await prisma.user.findFirst({
            where: {
                alias_id: (req.cookies.id) ? parseInt(req.cookies.id) : null,
                key: (req.cookies.key) ? req.cookies.key : ""
            }
        });
    
        return res.render("pages/home", {
            user: user,
            cid: cfg.Auth.ClientID,
            uri: cfg.Auth.RedirectURI
        });
    });
    
    router.post("/upload", multer.single("file"), async (req, res) => {
        const auth: string[] | undefined = (req.headers["authorization"]) ? new Buffer(req.headers["authorization"], "base64").toString("utf-8").split(':') : undefined;
    
        var user: User | null = await prisma.user.findFirst({
            where: {
                alias_id: (auth) ? parseInt(auth[1]) : (req.cookies.id) ? parseInt(req.cookies["id"]) : null,
                key: (auth) ? auth[2] : (req.cookies.key) ? req.cookies["key"] : ""
            }
        });
    
        const image: Image = await prisma.image.create({
            data: {
                storage_id: req.file!.filename!,
                ext: mime.getExtension(req.file!.mimetype)!,
                userId: (user) ? user.id : null
            }
        });
    
        return res.send(`https://i.hmmtodayiwill.ru/${image.id}`).status(200);
    });


    router.get("/:imageId", async (req, res) => {
        const auth: string[] | undefined = (req.headers["authorization"]) ? new Buffer(req.headers["authorization"], "base64").toString("utf-8").split(':') : undefined;

        var user: User | null = await prisma.user.findFirst({
            where: {
                alias_id: (auth) ? parseInt(auth[0]) : (req.cookies.id) ? parseInt(req.cookies["id"]) : null,
                key: (auth) ? auth[1] : (req.cookies.key) ? req.cookies["key"] : ""
            }
        });

        const image: Image | null = await prisma.image.findFirst({
            where: {
                id: req.params.imageId
            }
        });

        if (!image) {
            return res.json({
                status: 404,
                reason: "Image ID " + req.params.imageId + " not found in database!"
            }).status(404);
        }

        if (image.is_hidden) {
            if (user) {
                if (image.userId !== user.id) {
                    return res.json({
                        status: 401,
                        reason: "Image ID " + req.params.imageId + " have a hide flag."
                    }).status(401);
                } else {
                    return res.sendFile(`${__dirname}/static/images/${image.storage_id}`);
                }
            } else {
                return res.json({
                    status: 401,
                    reason: "Image ID " + req.params.imageId + " have a hide flag."
                }).status(401);
            }
        }

        return res.sendFile(`${dirPath}/static/images/${image.storage_id}`);
    });

    return router;
}

export default ImageRouter;