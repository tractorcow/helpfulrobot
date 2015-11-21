<?php

namespace SilverStripe\HelpfulRobot;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class GodClass
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $githubUser;

    /**
     * @var string
     */
    private $githubEmail;

    /**
     * @var string
     */
    private $githubToken;

    /**
     * @var string
     */
    private $githubHost;

    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * @param ClientInterface $client
     * @param string $githubUser
     * @param string $githubEmail
     * @param string $githubToken
     * @param string $githubHost
     * @param string $repositoryPath
     */
    public function __construct(ClientInterface $client, $githubUser, $githubEmail, $githubToken, $githubHost, $repositoryPath)
    {
        $this->client = $client;
        $this->githubUser = $githubUser;
        $this->githubEmail = $githubEmail;
        $this->githubToken = $githubToken;
        $this->githubHost = $githubHost;
        $this->repositoryPath = $repositoryPath;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $options
     *
     * @return ResponseInterface
     */
    public function request($method, $endpoint, array $options = [])
    {
        $endpoint = "https://api.github.com/{$endpoint}";

        $options = array_merge([
            "auth" => [$this->githubUser, $this->githubToken],
        ], $options);

        return $this->client->request($method, $endpoint, $options);
    }

    /**
     * Forks upstream repository, in the format "{user}/{repo}".
     *
     * @param string $upstream
     *
     * @return ResponseInterface
     */
    public function forkRepository($upstream)
    {
        return $this->request("POST", "repos/{$upstream}/forks");
    }

    /**
     * Clones origin repository, in the format "{user}/{repo}". Sets upstream
     * repositories, in the format "{user}/{repo}".
     *
     * @param string $origin
     * @param string $upstream
     * @param string $folder
     */
    public function cloneRepository($origin, $upstream, $folder)
    {
        exec("rm -rf {$this->repositoryPath}/{$folder}");
        exec("git clone git@{$this->githubHost}:{$origin}.git {$this->repositoryPath}/{$folder}");

        $this->changeDirectory($folder);

        exec("git config user.name '{$this->githubUser}'");
        exec("git config user.email '{$this->githubEmail}'");

        exec("git remote rm origin");
        exec("git remote add origin git@{$this->githubHost}:{$origin}.git");
        exec("git remote rm upstream");
        exec("git remote add upstream git@{$this->githubHost}:{$upstream}.git");
    }

    /**
     * @var string
     */
    public function changeDirectory($folder)
    {
        chdir("{$this->repositoryPath}/{$folder}");
    }

    /**
     * Creates a new branch from an upstream branch.
     *
     * @param string $target
     * @param string $source
     */
    public function createBranch($target, $source = "master")
    {
        exec("git fetch upstream");
        exec("git checkout upstream/{$source}");
        exec("git branch -D {$target}");
        exec("git checkout -b {$target}");
    }

    /**
     * Commits changes to files, in the format "file1.txt file2.txt".
     *
     * @param string $files
     * @param string $message
     */
    public function commitChanges($files, $message)
    {
        exec("git add {$files}");
        exec("git commit {$files} -m '{$message}'");
    }

    /**
     * Pushes changes to origin branch (for pull request).
     *
     * @param string $branch
     */
    public function pushChanges($branch)
    {
        exec("git push -fu origin {$branch}");
    }
}
