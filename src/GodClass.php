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
        exec("rm -rf {$this->repositoryPath}/{$folder} 2> /dev/null");
        exec("git clone git@{$this->githubHost}:{$origin}.git {$this->repositoryPath}/{$folder} 2> /dev/null");

        $this->changeDirectory($folder);

        exec("git config user.name '{$this->githubUser}' 2> /dev/null");
        exec("git config user.email '{$this->githubEmail}' 2> /dev/null");

        exec("git remote rm origin 2> /dev/null");
        exec("git remote add origin git@{$this->githubHost}:{$origin}.git 2> /dev/null");
        exec("git remote rm upstream 2> /dev/null");
        exec("git remote add upstream git@{$this->githubHost}:{$upstream}.git 2> /dev/null");
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
        exec("git fetch upstream 2> /dev/null");
        exec("git checkout upstream/{$source} 2> /dev/null");
        exec("git branch -D {$target} 2> /dev/null");
        exec("git checkout -b {$target} 2> /dev/null");
    }

    /**
     * Commits changes to files, in the format "file1.txt file2.txt" or "-a".
     *
     * @param string $files
     * @param string $message
     */
    public function commitChanges($files, $message)
    {
        exec("git add {$files} 2> /dev/null");
        exec("git commit {$files} -m '{$message}' 2> /dev/null");
    }

    /**
     * Pushes changes to origin branch (for pull request).
     *
     * @param string $branch
     */
    public function pushChanges($branch)
    {
        exec("git push -fu origin {$branch} 2> /dev/null");
    }
}
