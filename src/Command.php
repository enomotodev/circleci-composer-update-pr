<?php

namespace Enomotodev\CircleCIComposerUpdatePr;

use Exception;
use DateTime;

class Command
{
    /**
     * @return void
     */
    public static function main()
    {
        $command = new static;

        $command->run($_SERVER['argv']);
    }

    /**
     * @param  array $argv
     * @return void
     */
    public function run($argv)
    {
        if (count($argv) !== 4) {
            fwrite(STDERR, 'Invalid arguments.' . PHP_EOL);

            exit(1);
        }

        list(, $name, $email, $base) = $argv;

        system('composer update --no-progress --no-suggest');

        $now = new DateTime('now');
        $branch = 'composer-update-' . $now->format('YmdHis');

        if (strpos(system('git status -sb'), 'composer.lock') === false) {
            fwrite(STDOUT, 'No changes.' . PHP_EOL);
            exit(0);
        }

        $accessToken = getenv('GITHUB_ACCESS_TOKEN');
        $repositoryName = getenv('CIRCLE_PROJECT_REPONAME');
        $repositoryFullName = getenv('CIRCLE_PROJECT_USERNAME') . '/' . $repositoryName;

        $this->setupGit($accessToken, $repositoryFullName, $name, $email);

        $json = system('$COMPOSER_HOME/vendor/bin/composer-lock-diff --json');
        $diff = json_decode($json, true);

        $text = '';
        foreach (['changes', 'changes-dev'] as $key) {
            if (!empty($diff[$key])) {
                $text .= "### {$key}" . PHP_EOL;
                foreach ($diff[$key] as $packageName => $value) {
                    $text .= "- {$packageName}: ";
                    if ($value[2]) {
                        $text .= "[`{$value[0]}...{$value[1]}`]({$value[2]})";
                    } else {
                        $text .= "`{$value[0]}...{$value[1]}`";
                    }
                    $text .= PHP_EOL;
                }
            }
        }

        $this->createBranch($branch);
        $this->createPullRequest($accessToken, $repositoryName, $name, $base, $branch, $now, $text);
    }

    /**
     * @param  string $accessToken
     * @param  string $repositoryFullName
     * @param  string $name
     * @param  string $email
     * @return void
     */
    private function setupGit($accessToken, $repositoryFullName, $name, $email)
    {
        $remote = "https://{$accessToken}@github.com/{$repositoryFullName}/";

        system("git remote add github-url-with-token {$remote}");
        system("git config user.name {$name}");
        system("git config user.email {$email}");
    }

    /**
     * @param  string $branch
     * @return void
     */
    private function createBranch($branch)
    {
        system("git add composer.lock");
        system("git commit -m '$ composer update'");
        system("git branch -M {$branch}");
        system("git push -q github-url-with-token {$branch}");
    }

    /**
     * @param  string $accessToken
     * @param  string $repositoryName
     * @param  string $name
     * @param  string $base
     * @param  string $branch
     * @param  \DateTime $now
     * @param  string $text
     * @return void
     */
    private function createPullRequest($accessToken, $repositoryName, $name, $base, $branch, $now, $text)
    {
        $title = 'composer update at ' . $now->format('Y-m-d H:i:s T');

        $client = new \Github\Client();
        $client->authenticate($accessToken, null, \Github\Client::AUTH_URL_TOKEN);
        /** @var \Github\Api\PullRequest $api */
        $api = $client->api('pull_request');
        try {
            $api->create($name, $repositoryName, [
                'base' => $base,
                'head' => $branch,
                'title' => $title,
                'body' => '## Updated Composer Packages' . PHP_EOL . PHP_EOL . $text,
            ]);
        } catch (Exception $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    }
}
