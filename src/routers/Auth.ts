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

import { Router, CookieOptions } from "express";
import { PrismaClient, User } from "@prisma/client";
import { randomBytes } from "crypto";
import axios from "axios";
import { Logger } from "tslog";

const log: Logger = new Logger({name: "authlog"});

function AuthRouter(dirPath: string, cfg: {[key: string]: any}, prisma: PrismaClient): Router {
    const router: Router = Router();

    router.get("/twitch", async (req, res) => {
        if (!("code" in req.query)) {
            return res.json({
                status: 400,
                reason: "\"code\" query not found."
            }).status(400);
        }

        try {
            const req_token = await axios.post("https://id.twitch.tv/oauth2/token", `client_id=${cfg.Auth.ClientID}&client_secret=${cfg.Auth.ClientSecret}&code=${req.query.code}&grant_type=authorization_code&redirect_uri=${cfg.Auth.RedirectURI}`, {headers: {"Content-Type": "application/x-www-form-urlencoded"}});

            const ttv_user = await axios.get("https://api.twitch.tv/helix/users", {
                responseType: "json",
                headers: {
                    "Authorization": `Bearer ${req_token.data.access_token}`,
                    "Client-Id": cfg.Auth.ClientID
                }
            });

            const user_data = ttv_user.data.data[0];

            const user: User | null = await prisma.user.findFirst({
                where: {
                    alias_id: parseInt(user_data.id)
                }
            });

            const key: string = randomBytes(16).toString("hex");
            const cookie_opts: CookieOptions = {
                httpOnly: false,
                secure: true,
                sameSite: "lax"
            };

            if (!user) {
                await prisma.user.create({
                    data: {
                        alias_id: parseInt(user_data.id),
                        name: user_data.login,
                        desc: user_data.description,
                        pic: user_data.profile_image_url,
                        key: key
                    }
                });

                res.cookie("key", key, cookie_opts);
                res.cookie("id", user_data.id, cookie_opts);
            } else {
                await prisma.user.update({
                    where: {id: user.id},
                    data: {
                        name: user_data.login,
                        desc: user_data.description,
                        pic: user_data.profile_image_url,
                        key: key
                    }
                });

                res.cookie("key", key, cookie_opts);
                res.cookie("id", user_data.id, cookie_opts);
            }
            
            res.redirect("/me");
        } catch (err: any) {
            res.json({
                status: (err.response.data.status) ? err.response.data.status : 400,
                message: (err.response.data.message) ? err.response.data.message : "Bad request."
            }).status((err.response.data.status) ? err.response.data.status : 400);
        }
    });

    return router;
}

export default AuthRouter;