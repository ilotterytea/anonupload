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

import CLI from "./src/CLI";
import Main from "./src/Main";
import Ini from "ini";
import { readFileSync, writeFileSync } from "fs";
import { Logger } from "tslog";

const log: Logger = new Logger({name: "init"});
const cli: Record<string, string | boolean> = CLI();

if (cli.init) {
    const cfg_template = {
        Auth: {
            ClientID: "",
            ClientSecret: "",
            RedirectURI: ""
        },
        Certificate: {
            Key: "",
            Cert: "",
            Ca: ""
        },
        Ports: {
            HTTP: "80",
            HTTPS: "443"
        }
    }

    writeFileSync("config.ini", Ini.stringify(cfg_template), {encoding: "utf-8"});
    log.debug("Config file is generated! Exiting...");
    process.exit(0);
}

const config: {[key: string]: any} = Ini.parse(readFileSync("./config.ini", {encoding: "utf-8"}));

Main(__dirname, config, cli);