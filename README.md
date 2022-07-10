<!--
 Copyright 2022 ilotterytea
 
 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at
 
     http://www.apache.org/licenses/LICENSE-2.0
 
 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
-->

<div align="center">
    <h1>
        <img src="./static/webimg/favicon.png" width=32> File Hoster!
            <br>
            <a href="https://wakatime.com/badge/user/09f67b1c-0691-482a-a1d4-e4751e6962de/project/eda75115-4dad-4ada-995a-f1379687f257"><img src="https://wakatime.com/badge/user/09f67b1c-0691-482a-a1d4-e4751e6962de/project/eda75115-4dad-4ada-995a-f1379687f257.svg?style=plastic" alt="wakatime"></a>
    </h1>

</div>
A small file hoster on ExpressJS.

## Installation:
1. Use the script...
    1. as a standalone app:
    ```bash
    $ git clone https://github.com/notdankenough/fh.git
    $ cd fh
    $ npm install
    $ npm run start
    ```
    2. as a module for your project:
    ```bash
    $ git submodule add https://github.com/notdankenough/fh.git
    ```
    and you can import it as: `import SRouter from "./fh/router";`

## Use in other tools:
1. [ <img src="https://camo.githubusercontent.com/6ca305d42786c9dbd0b76f5ade013601b080d71a598e881b4349dff2eafae6c7/68747470733a2f2f666f757274662e636f6d2f696d672f63686174746572696e6f2d69636f6e2d36342e706e67" width=24> Chatterino 2 (by fourtf, pajlada, and more...)](https://github.com/chatterino/chatterino2):
    1. Go to `Settings -> External Tools -> Image Uploader` and paste the values in the relevant fields:

    | Field | Value | Example |
    | ---- | ------ | ------- |
    | Request URL: | `<YOUR_DOMAIN>`/dank_upload | https://hmmtodayiwill.ru/i/dank_upload
    | Form field: | file |
    | Extra Headers: | no-redirect: true | 
    | Image Link: | `<YOUR_DOMAIN>`/i/{name} | https://hmmtodayiwill.ru/i/{file}
