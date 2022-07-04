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

import { Command } from "commander";
import chalk from "chalk";
import express from "express";

import pck from "./package.json";
import SRouter from "./router";
import { existsSync, mkdirSync, writeFileSync } from "fs";

main();

/**
 * The "web server" script.
 */
function main() {
    // Obtaining the options that have been provided in the command line interface:
    const cli = CLI();
    const opts = cli.opts();

    try {
        // Create the necessary files if the script was started with the --init option:
        if (opts.init) {
            if (!(existsSync("./options.json"))) {
                writeFileSync("./options.json", JSON.stringify({
                    allowedMIMETypes: [
                        "image/jpeg",
                        "image/png",
                        "image/svg+xml",
                        "image/tiff",
                        "image/webp",
                        "image/gif",
                        "video/mp4"],
                    maxFileSizeInBytes: 13107200
                }, null, 2), {encoding: "utf-8"});
                console.debug(chalk.green("The options file created!"));
            }
            return;
        }

        // Run the server if the script was started with the --standalone option:
        if (opts.standalone) {
            // ExpressJS instance:
            const App = express();
            
            // Using an image loader with custom route (--route <value>) on the server:
            App.use(opts.route, SRouter);

            // Using a static folder. Also, this will be used when viewing uploaded pictures.
            App.use(express.static(`${__dirname}/static`));

            // Starting the server on the specified port:
            App.listen(opts.port, () => {
                console.debug(chalk.bgGreen("Server (Image hoster) running on port ", opts.port));
            });
        }
    } catch (err: any) {
        console.log(chalk.bgRed(err));
    }
}

/**
 * Silly command-line interface.
 */
function CLI() {
    // New Command instance:
    const Program = new Command();

    // Setting the information about Program:
    Program
        .name(pck.displayName)
        .description(pck.description)
        .version(pck.version);
    
    // Create an option to run this script as a web app:
    Program
        .option("--standalone", "Run the script as a web app.", false)
        .alias("-s");
    
    // The option to be specified as the port in the web app:
    Program
        .option("--port <value>", "Set the port for standalone app.", "8080")
        .alias("-p");
    
    // The route for our image uploader:
    Program
        .option("--route <value>", "Set the route for standalone app.", "/")
        .alias("-r");
    
    // Initialize the first setup of image uploader:
    Program
        .option("--init", "Create the necessary files for image uploader.", false);
    
    // Parse the process arguments:
    Program.parse(process.argv);

    return Program;
}