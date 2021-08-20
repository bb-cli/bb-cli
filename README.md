# Bitbucket Rest Cli

Use bitbucket from command line.
With this app you can see pull request, pipelines, branchs etc. from your terminal.



# Installation
__NOTE__: before install this package, you should have php installed on your machine.

* Download standalone binary from [releases](https://github.com/bb-cli/bb-cli/releases)
* Move downloaded file to path like `mv bb.phar /usr/local/bin/bb`

# Help Command
Help command lists available methods.
`bb help`
```
Available actions:
[
    "pr",
    "pipeline",
    "branch",
    "auth"
]
```

`bb pr help`
```
Available methods:
[
    "list, l",
    "diff, d",
    "commits, c",
    "approve, a",
    "no-approve, na",
    "request-changes, rc",
    "no-request-changes, nrc",
    "decline",
    "merge, m",
    "create"
]
```

# Auth Command

## Save
`bb auth` or `bb auth save` command to save auth info.

## Show
You can see auth info with using show command. `bb auth show`

--- 

# Pr Command

## List
`bb pr list [branch]` list pull request for repository, You may add optional `brach` parameter to see pull request that made for given branch. For example can can use this command to see pull request that destination is dev branch `bb pr list dev`

## Merge 
`bb pr merge <pr-id>` merge pull request


---

# Pipeline Command

## Latest
`bb pipeline latest` gets latest pipeline info (status, state, etc.).

## Get
`bb pipeline get <pipeline-id>` gets pipeline info for given id (status, state, etc.).

## Wait
`bb pipeline wait <pipeline-id>` waits given pipeline to finish (checks every two seconds).


---

# Branch Command

## List
`bb branch list` lists branchs .

## Name
`bb branch name feature` list branches that branch name contains "feature".

## User
`bb branch user semih` list branches that latest commit user name contains "semih".
