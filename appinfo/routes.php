<?php

return [
    "routes" => [
        ["name" => "player#index", "url" => "/{fileId}", "verb" => "GET"],
        ["name" => "viewer#public_page", "url" => "/s/{shareToken}", "verb" => "GET"],
        ["name" => "viewer#public_file", "url" => "/ajax/shared/{fileId}", "verb" => "GET"],
        ["name" => "settings#getsettings", "url" => "/ajax/settings", "verb" => "GET"],
    ]
];
