# circleci-composer-update-pr

[![Latest Stable Version](https://poser.pugx.org/enomotodev/circleci-composer-update-pr/v/stable.png)](https://packagist.org/packages/enomotodev/circleci-composer-update-pr)

## Installation

```
$ composer require enomotodev/circleci-composer-update-pr
```

## Prerequisites

The application on which you want to run continuous composer update must be configured to be built on CircleCI.

## Usage

### Setting GitHub personal access token to CircleCI

GitHub personal access token is required for sending pull requests to your repository.

1. Go to [your account's settings page](https://github.com/settings/tokens) and generate a personal access token with "repo" scope
1. On CircleCI dashboard, go to your application's "Project Settings" -> "Environment Variables"
1. Add an environment variable `GITHUB_ACCESS_TOKEN` with your GitHub personal access token

### Configure circle.yml

Configure your `circle.yml` or `.circleci/config.yml` to run `circleci-composer-update-pr`, for example:

```yaml
version: 2
jobs:
  build:
    # snip
  continuous_composer_update:
    docker:
      - image: composer:latest
    working_directory: /work
    steps:
      - run:
          name: Install System Dependencies
          command: apk add --update --no-cache tzdata
      - run:
          name: Set timezone to Asia/Tokyo
          command: cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
      - checkout
      - restore_cache:
          name: Restore composer cache
          keys:
            - composer-{{ .Environment.COMMON_CACHE_KEY }}-{{ checksum "composer.lock" }}
            - composer-{{ .Environment.COMMON_CACHE_KEY }}-
      - run:
          name: Setup requirements for continuous composer update
          command: composer global require enomotodev/circleci-composer-update-pr
      - deploy:
          name: Continuous composer update
          command: $COMPOSER_HOME/vendor/bin/circleci-composer-update-pr <username> <email> master

workflows:
  version: 2
  build:
    jobs:
      - build:
          # snip
  nightly:
    triggers:
      - schedule:
          cron: "00 10 * * 5"
          filters:
            branches:
              only: master
    jobs:
      - continuous_composer_update
```

NOTE: Please make sure you replace `<username>` and `<email>` with yours.


## CLI command references

General usage:

```
$ circleci-compsoser-update-pr <git username> <git email address> <git base branch>
```

## License

circleci-compsoser-update-pr is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
