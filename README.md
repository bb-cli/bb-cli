# Bitbucket Rest API CLI

Use Bitbucket from command line. With this app you can see pull request, pipelines, branches etc. from your terminal.

![Bitbucket CLI](ss.gif)

## Installation

__NOTE__: Before install this package, you should have PHP installed on your machine.

* Download standalone binary from [releases](https://github.com/bb-cli/bb-cli/releases)
* Move downloaded file to path like `mv bb.phar /usr/local/bin/bb`
* Let's start `bb help`

## Usage

[View the documentation](https://bb-cli.github.io) for usage information.

## Notes on autocomplete arguments
You may add something like this to add auto completion feature.
```bash
_bb_autocomplete() {
    local pipeline_commands="get latest wait"
    local pr_commands="list diff commits approve no-approve request-changes no-request-changes decline merge create"
    local branch_commands="list user name"
    local auth_commands="save show"

    _arguments "1: :(pr pipeline branch auth)" "2: :(help $pipeline_commands $pr_commands $branch_commands $auth_commands)"
}

compdef _bb_autocomplete bb
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
