<?php
define('GEN_TIMESTAMP', floor(microtime(true) * 1000));
define("CONFIG_FILE_PATH", "{$_SERVER['DOCUMENT_ROOT']}/anonupload.json");

$cfg = [
    "instance" => [
        "name" => $_SERVER['HTTP_HOST'],
        "mirrors" => [],
        "url" => (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]",
        "footerlinks" => [],
    ],
    "storage" => [
        "type" => "file"
    ],
    "database" => [
        "driver" => "mysql",
        "host" => "localhost",
        "port" => 3306,
        "name" => "anonupload",
        "user" => "default",
        "pass" => "default"
    ],
    "driver" => [
        "ruffle" => false,
        "chart" => false
    ],
    "filecatalog" => [
        "public" => true,
        "limit" => 50,
        "includemimetypes" => [],
    ],
    "supriseme" => [
        "enable" => true,
        "order" => "rand()"
    ],
    "files" => [
        "fancyview" => true,
        "countviews" => true,
        "showuploadtime" => true,
        "showviews" => true,
        "displayhtml" => true,
        "title" => true,
        "directory" => "{$_SERVER['DOCUMENT_ROOT']}/userdata/uploads",
        "url" => "/userdata/uploads",
        "deletion" => true,
        "deletionkeylength" => 16,
        "defaultvisibility" => 1
    ],
    "upload" => [
        "acceptedmimetypes" => [
            '3g2' => 'video/3gpp2',
            '3gp' => 'video/3gp',
            '7z' => 'application/x-7z-compressed',
            'aac' => 'audio/x-acc',
            'ac3' => 'audio/ac3',
            'ai' => 'application/postscript',
            'aif' => 'audio/x-aiff',
            'au' => 'audio/x-au',
            'avi' => 'video/x-msvideo',
            'bin' => 'application/macbinary',
            'bmp' => 'image/bmp',
            'cdr' => 'application/cdr',
            'cpt' => 'application/mac-compactpro',
            'crl' => 'application/pkix-crl',
            'crt' => 'application/x-x509-ca-cert',
            'csv' => 'text/x-comma-separated-values',
            'dcr' => 'application/x-director',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dvi' => 'application/x-dvi',
            'eml' => 'message/rfc822',
            'exe' => 'application/x-msdownload',
            'f4v' => 'video/x-f4v',
            'flac' => 'audio/x-flac',
            'flv' => 'video/x-flv',
            'gif' => 'image/gif',
            'gpg' => 'application/gpg-keys',
            'gtar' => 'application/x-gtar',
            'gzip' => 'application/x-gzip',
            'hqx' => 'application/mac-binhex40',
            'ico' => 'image/x-icon',
            'ics' => 'text/calendar',
            'jar' => 'application/java-archive',
            'jp2' => 'image/jp2',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'json' => 'application/json',
            'kml' => 'application/vnd.google-earth.kml+xml',
            'kmz' => 'application/vnd.google-earth.kmz',
            'log' => 'text/plain',
            'm4a' => 'audio/x-m4a',
            'm4u' => 'application/vnd.mpegurl',
            'mid' => 'audio/midi',
            'mif' => 'application/vnd.mif',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'oda' => 'application/oda',
            'ogg' => 'audio/ogg',
            'otf' => 'font/otf',
            'p10' => 'application/x-pkcs10',
            'p12' => 'application/x-pkcs12',
            'p7a' => 'application/x-pkcs7-signature',
            'p7c' => 'application/pkcs7-mime',
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/pkcs7-signature',
            'pdf' => 'application/pdf',
            'pem' => 'application/x-x509-user-cert',
            'pgp' => 'application/pgp',
            'png' => 'image/png',
            'ppt' => 'application/powerpoint',
            'doc' => 'application/msword',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'psd' => 'application/x-photoshop',
            'ra' => 'audio/x-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'rar' => 'application/x-rar',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'rsa' => 'application/x-pkcs7',
            'rtf' => 'text/rtf',
            'rtx' => 'text/richtext',
            'rv' => 'video/vnd.rn-realvideo',
            'sit' => 'application/x-stuffit',
            'smil' => 'application/smil',
            'srt' => 'text/srt',
            'svg' => 'image/svg+xml',
            'swf' => 'application/x-shockwave-flash',
            'tar' => 'application/x-tar',
            'tgz' => 'application/x-gzip-compressed',
            'tiff' => 'image/tiff',
            'ttf' => 'font/ttf',
            'txt' => 'text/plain',
            'vcf' => 'text/x-vcard',
            'vlc' => 'application/videolan',
            'vtt' => 'text/vtt',
            'wav' => 'audio/x-wav',
            'wbxml' => 'application/wbxml',
            'webm' => 'video/webm',
            'webp' => 'image/webp',
            'wma' => 'audio/x-ms-wma',
            'wmlc' => 'application/wmlc',
            'wmv' => 'video/x-ms-wmv',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'xl' => 'application/excel',
            'xls' => 'application/msexcel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml' => 'application/xml',
            'xsl' => 'text/xsl',
            'xspf' => 'application/xspf+xml',
            'z' => 'application/x-compress',
            'zip' => 'application/zip',
            'zsh' => 'text/x-scriptzsh',
            'lua' => 'text/x-lua'
        ],
        "verifymimetype" => true,
        "stripexif" => true,
        "idcharacters" => "ABCDEF",
        "idlength" => 5,
        "idprefix" => false,
        "customid" => true,
        "customidregex" => "/^[A-za-z0-9]+$/",
        "customidlength" => 12,
        "zipwebapps" => false,
        "titlelength" => 100,
        "removeletterboxes" => false,
        "expiration" => [
            'ne' => 'Never',
            '14d' => '2 weeks',
            '7d' => 'a week',
            '3d' => '3 days',
            '1d' => 'a day',
            '12h' => '12 hours',
            '3h' => '3 hours',
            '5m' => '5 minutes',
            're' => 'Burn after seeing'
        ],
    ],
    "externalupload" => [
        "enable" => true,
        "maxduration" => 60 * 5,
        "quality" => "worst"
    ],
    "thumbnails" => [
        "enable" => true,
        "directory" => "{$_SERVER['DOCUMENT_ROOT']}/userdata/thumbnails",
        "url" => "/userdata/thumbnails",
        "width" => 128,
        "height" => 128
    ],
    "metadata" => [
        "directory" => "{$_SERVER['DOCUMENT_ROOT']}/userdata/metadata"
    ],
    "report" => [
        "enable" => false,
        "directory" => "{$_SERVER['DOCUMENT_ROOT']}/userdata/reports"
    ],
    "moderation" => [
        "path" => "{$_SERVER['DOCUMENT_ROOT']}/.anonuploadpasswd",
        "banfiles" => true,
        "hashpath" => "{$_SERVER['DOCUMENT_ROOT']}/.bannedhashes"
    ],
    "users" => [
        "path" => "{$_SERVER['DOCUMENT_ROOT']}/.anonuploadpasswd",
        "cookietime" => 60 * 60 * 24 * 30,
        "allowregistration" => true,
        "usernameregex" => '/^[A-za-z0-9]+$/',
        "usernameminlength" => 3,
        "usernamemaxlength" => 25,
        "passwordminlength" => 8
    ],
    "stats" => [
        "enable" => true,
        "lastfiles" => true,
        "mostviewed" => true,
        "disksize" => 0
    ]
];

if (file_exists(CONFIG_FILE_PATH)) {
    $c = json_decode(file_get_contents(CONFIG_FILE_PATH), true);
    foreach ($cfg as $sk => $sv) {
        if (!is_array($sv) || !array_key_exists($sk, $c)) {
            continue;
        }

        foreach ($sv as $k => $v) {
            if (array_key_exists($k, $c[$sk])) {
                $v = $c[$sk][$k];

                if (is_array($cfg[$sk][$k])) {
                    if ($sk === "filecatalog" && $k === "includemimetypes") {
                        $v = explode(' ', $v);
                    } else {
                        $lines = explode("\n", $v);
                        $v = [];
                        foreach ($lines as $line) {
                            $parts = explode('=', $line, 2);
                            if (count($parts) != 2) {
                                continue;
                            }
                            $v[$parts[0]] = $parts[1];
                        }
                    }
                }

                $cfg[$sk][$k] = $v;
            }
        }
    }
}

if (!empty($cfg['database']['host'])) {
    $cfg['database']['url'] = "{$cfg['database']['driver']}:host={$cfg['database']['host']};dbname={$cfg['database']['name']};port={$cfg['database']['port']}";
}

define("CONFIG", $cfg);
define("CLIENT_REQUIRES_JSON", isset($_SERVER["HTTP_ACCEPT"]) && $_SERVER["HTTP_ACCEPT"] == "application/json");

// library existence check
$imagemagick = null;

if (shell_exec("which magick")) {
    $imagemagick = [
        "identify" => "magick identify",
        "convert" => "magick"
    ];
} else if (shell_exec("which identify") && shell_exec("which convert")) {
    $imagemagick = [
        "identify" => "identify",
        "convert" => "convert"
    ];
}

define("IMAGEMAGICK_COMMAND", $imagemagick);