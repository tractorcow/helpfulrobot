<?php

require("vendor/autoload.php");

$config = require("config.php");
$path = realpath("./repositories");

$branches = [
    "master",
    "convert-to-psr-2",
    "add-standard-scrutinizer-config",
    "add-standard-travis-config",
    "add-standard-editor-config",
    "add-standard-license",
    "add-standard-git-attributes",
    "add-standard-code-of-conduct",
];

$repositories = array_filter(require("repositories.php"), function($repository) {
    return false;
});

$go = new SilverStripe\HelpfulRobot\GodClass(
    new GuzzleHttp\Client(),
    $config["github"]["user"],
    $config["github"]["email"],
    $config["github"]["token"],
    $config["github"]["host"],
    $path
);

$backupBrain = function() use ($go) {
    $go->commitChanges(".", "Backing up brain");
    $go->pushChanges("master");
};

$backupBrain();
exit();

$removeUpstreamBranches = function() use ($branches, $repositories, $go) {
    foreach ($repositories as $repository) {
        $go->changeDirectory($repository["folder"]);

        $response = $go->request(
            "GET", "repos/{$repository["origin"]}/branches"
        );

        $body = (string) $response->getBody();
        $json = json_decode($body, true);

        foreach ($json as $branch) {
            $name = $branch["name"];

            if (!in_array($name, $branches)) {
                print "delete {$name} on {$repository["module"]}\n";
                exec("git branch -D {$name}");
                exec("git push origin :refs/heads/{$name}");
            }
        }
    }
};

$makeRepositories = function() use ($repositories, $go) {
    foreach ($repositories as $repository) {
        $go->forkRepository($repository["upstream"]);

        sleep(5);

        $go->cloneRepository(
            $repository["origin"],
            $repository["upstream"],
            $repository["folder"]
        );
    }
};

$convertToPsr2 = function() use ($repositories, $go) {
    foreach ($repositories as $repository) {
        $go->changeDirectory($repository["folder"]);
        $go->createBranch("convert-to-psr-2");

        exec("php-cs-fixer fix --level=psr2 --fixers=-psr0,-join_function code");
        exec("php-cs-fixer fix --level=psr2 --fixers=-psr0,-join_function tests");

        $go->commitChanges(".", "Converted to PSR-2");
        $go->pushChanges("convert-to-psr-2");
    }
};

$addStandardScrutinizerConfig = function() use ($path, $repositories, $go) {
    foreach ($repositories as $repository) {
        $folder = $repository["folder"];

        $go->changeDirectory($folder);
        $go->createBranch("add-standard-scrutinizer-config");

        copy(realpath(__DIR__ . "/templates/.scrutinizer.yml"), "{$path}/{$folder}/.scrutinizer.yml");

        $go->commitChanges(".scrutinizer.yml", "Added standard Scrutinizer config");
        $go->pushChanges("add-standard-scrutinizer-config");
    }
};

$addStandardTravisConfig = function() use ($path, $repositories, $go) {
    foreach ($repositories as $repository) {
        $folder = $repository["folder"];

        $go->changeDirectory($folder);
        $go->createBranch("add-standard-travis-config");

        $contents = file_get_contents(realpath(__DIR__ . "/templates/.travis.yml"));
        $contents = str_replace("{module}", $repository["module"], $contents);
        file_put_contents("{$path}/{$folder}/.travis.yml", $contents);

        $go->commitChanges(".travis.yml", "Added standard Travis config");
        $go->pushChanges("add-standard-travis-config");
    }
};

$addStandardEditorConfig = function() use ($path, $repositories, $go) {
    foreach ($repositories as $repository) {
        $folder = $repository["folder"];

        $go->changeDirectory($folder);
        $go->createBranch("add-standard-editor-config");

        copy(realpath(__DIR__ . "/templates/.editorconfig"), "{$path}/{$folder}/.editorconfig");

        $go->commitChanges(".editorconfig", "Added standard editor config");
        $go->pushChanges("add-standard-editor-config");
    }
};

$addStandardLicense = function() use ($path, $repositories, $go) {
    foreach ($repositories as $repository) {
        $folder = $repository["folder"];

        $go->changeDirectory($folder);
        $go->createBranch("add-standard-license");

        exec("rm -f $(ls | grep -i '^license\.md')");
        exec("rm -f $(ls | grep -i '^license')");
        exec("rm -f $(ls | grep -i '^licence\.md')");
        exec("rm -f $(ls | grep -i '^licence')");

        $file = realpath(__DIR__) . "/templates/license-mit.md";
        $license = $repository["license"];

        if ($license === "BSD-2-Clause") {
            $file = realpath(__DIR__) . "/templates/license-bsd-2.md";
        }

        if ($license === "BSD-3-Clause") {
            $file = realpath(__DIR__) . "/templates/license-bsd-3.md";
        }

        $contents = file_get_contents($file);
        $contents = str_replace("{owner}", $repository["owner"], $contents);
        $contents = str_replace("{year}", date("Y"), $contents);
        file_put_contents("{$path}/{$folder}/license.md", $contents);

        $file = "{$path}/{$folder}/composer.json";
        $contents = file_get_contents($file);
        $json = json_decode($contents, true);

        if (!isset($json["license"]) || $json["license"] != $license) {
            print "The license should be: {$license}\n";
            exec("atom --wait --new-window composer.json");
        }

        $go->commitChanges(".", "Added standard license");
        $go->pushChanges("add-standard-license");
    }
};

$addStandardGitAttributes = function() use ($path, $repositories, $go) {
    foreach ($repositories as $repository) {
        $folder = $repository["folder"];

        $go->changeDirectory($folder);
        $go->createBranch("add-standard-git-attributes");

        copy(realpath(__DIR__ . "/templates/.gitattributes"), "{$path}/{$folder}/.gitattributes");

        $go->commitChanges(".gitattributes", "Added standard git attributes");
        $go->pushChanges("add-standard-git-attributes");
    }
};

$addStandardCodeOfConduct = function() use ($path, $repositories, $go) {
    foreach ($repositories as $repository) {
        $folder = $repository["folder"];

        $go->changeDirectory($folder);
        $go->createBranch("add-standard-code-of-conduct");

        copy(realpath(__DIR__ . "/templates/code-of-conduct.md"), "{$path}/{$folder}/code-of-conduct.md");

        $go->commitChanges("code-of-conduct.md", "Added standard code of conduct");
        $go->pushChanges("add-standard-code-of-conduct");
    }
};

$getRepositoriesForOrganisation = function($organisation) use ($repositories, $go) {
    $response = $go->request("GET", "orgs/{$organisation}/repos?type=public&per_page=1000");

    $body = (string) $response->getBody();
    $json = json_decode($body, true);

    $names = array_map(function($repository) {
        return $repository["upstream"];
    }, $repositories);

    foreach ($json as $remote) {
        if (!in_array($remote["full_name"], $names)) {
            print "untouched repository: {$remote["full_name"]}\n";
        }
    }
};

print_r($repositories);
exit();

$makeRepositories();
$removeUpstreamBranches();
exit();

$convertToPsr2();
$addStandardScrutinizerConfig();
$addStandardTravisConfig();
$addStandardEditorConfig();
$addStandardLicense();
$addStandardGitAttributes();
$addStandardCodeOfConduct();
exit();

$getRepositoriesForOrganisation("silverstripe");
$getRepositoriesForOrganisation("silverstripe-labs");
$getRepositoriesForOrganisation("open-sausages");
exit();
