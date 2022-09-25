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

import express from "express";

import cookieParser from "cookie-parser";
import bodyParser from "body-parser";

import { Logger } from "tslog";

import ProfileRouter from "./routers/Profile";
import AuthRouter from "./routers/Auth";
import ImageRouter from "./routers/Image";
import { PrismaClient } from "@prisma/client";
import { readFileSync } from "fs";

const log: Logger = new Logger({name: "main"});

function Main(dirPath: string, cfg: {[key: string]: any}, cli_options?: {[key: string]: any}): void {
    const App: express.Express = express();
    const prisma: PrismaClient = new PrismaClient();

    App.set("view engine", "ejs");
    App.set("views", `${dirPath}/static/ejs`);

    App.use(cookieParser());
    App.use(bodyParser.urlencoded({extended: false}));

    App.use("/me", ProfileRouter(dirPath, prisma));
    App.use("/auth", AuthRouter(dirPath, cfg, prisma));
    App.use("/", ImageRouter(dirPath, cfg, prisma));

    App.use(express.static(`${dirPath}/static`));

    if (cli_options) {
        App.listen(parseInt(cfg.Ports.HTTP), () => {
            log.info("Image hoster is running on port", cfg.Ports.HTTP);
        });
    } else {
        log.error("NO CLI OPTIONS PROVIDED!!!")
        process.exit(1);
    }
}

export default Main;