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
import { Router } from "express";

function ProfileRouter(dirPath: string, prisma: PrismaClient): Router {
    const router: Router = Router();

    router.get("/", async (req, res) => {
        if (!req.cookies.id || !req.cookies.key) {
            return res.redirect("/");
        }

        const user: User | null = await prisma.user.findFirst({
            where: {
                alias_id: parseInt(req.cookies.id),
                key: req.cookies.key
            }
        });

        if (!user) {
            return res.redirect("/");
        }

        var images: Image[] = await prisma.image.findMany({
            where: {
                userId: user.id
            },
            orderBy: {
                timestamp: "desc"
            }
        });

        const keyNotEncoded: string = `${user.id}:${user.alias_id}:${user.key}`;

        return res.render("pages/me", {
            user: user,
            images: images,
            authKey: Buffer.alloc(keyNotEncoded.length, keyNotEncoded).toString("base64")
        });
    });

    return router;
}

export default ProfileRouter;