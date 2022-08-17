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
        <img src="./static/img/favicon.png" width=32> <b>Pic</b>ture<b>Bin</b> <i>(formerly Dank File Hoster)</i>
            <br>
            <a href="https://wakatime.com/badge/user/09f67b1c-0691-482a-a1d4-e4751e6962de/project/eda75115-4dad-4ada-995a-f1379687f257"><img src="https://wakatime.com/badge/user/09f67b1c-0691-482a-a1d4-e4751e6962de/project/eda75115-4dad-4ada-995a-f1379687f257.svg?style=plastic" alt="wakatime"></a>
    </h1>

</div>
A small file hoster on ExpressJS.

## Dependencies:
1. Node.js
2. NPM
3. Typescript

## Installation:
1. Install from Git repository:
```bash
$ git clone https://github.com/notdankenough/picbin
$ cd picbin
```
2. Install the Node.js modules:
```bash
$ npm install
```
3. Run Prisma migrations. This will create the database:
```bash
$ npx prisma migrate dev
```
4. Build the app:
```bash
$ npm run build
```
5. Generate the configuration files. The program will create a `config.ini` file and you will need to insert the necessary values into their fields:
```bash
$ npm run init
```
6. Run the app:
```bash
$ npm run start
```
7. ???
8. PROFIT! Now you have your own dank image hoster.

## Use in other tools:
1. [ <img src="https://camo.githubusercontent.com/6ca305d42786c9dbd0b76f5ade013601b080d71a598e881b4349dff2eafae6c7/68747470733a2f2f666f757274662e636f6d2f696d672f63686174746572696e6f2d69636f6e2d36342e706e67" width=24> Chatterino (by fourtf, pajlada, and more...)](https://github.com/chatterino/chatterino2):
    1. Go to `Settings -> External Tools -> Image Uploader` and paste the values in the relevant fields:

    | Field | Value | Example |
    | ---- | ------ | ------- |
    | Request URL: | `<YOUR_DOMAIN>`/upload | https://i.hmmtodayiwill.ru/upload
    | Form field: | file |
    | Extra Headers: | `Authorization: <some Base64 shit>` | Authorization: SUZZT1VTRUVUSElTVklWT05aVUxVTA==

> * If you want to upload pictures under your account, you need to log in with your Twitch account [here](https://i.hmmtodayiwill.ru/).