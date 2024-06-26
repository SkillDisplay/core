# SkillDisplay Skill Management backend extension

This extension is the backend for managing skills. With this extension you receive all the tools for defining and managing Skills and SkillSets.
It provides an REST-like API for the MySkillDisplay app and third-party integrations.

Some public API endpoints can be accessed publicly without authentication. Others require an API key.
The public API endpoints are documented at https://documenter.getpostman.com/view/18067935/UV5c8uxh

## License

See LICENSE.txt provided with this package.

## Hosting requirements

For some PDF generation the executable `weasyprint` (https://weasyprint.readthedocs.io/en/stable/) is necessary on the server.

The site must have `$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['enforceValidation'] = false` for the API to work.

## Development

### Using yarn and gulp

Use yarn inside the docker container.

For convenience we provide `./Scripts/node.sh yarn`

#### Gulp tasks can be run with:

`./Scripts/node.sh yarn gulp <task>`

#### Building mails:

The mail templates use the Foundation Emails framework. So the source files (`Resources/Private/MailTemplatesSrc/`) need to be compiled.

`./Scripts/node.sh yarn gulp mail`

### Local testing and composer dependencies

`./Scripts/composer.sh upgrade -W` is your friend

#### SkillDisplay App (aka Frontend)
The SkillDisplay App will be released separately in the near future.

## Skill Management

### Methodology and Manuals
You can find articles on how to use the Visual Skill Editor for creating and managing your own SkillSets at the `SkillDisplay service desk` (https://skilldisplay.atlassian.net/servicedesk/customer/portal/2)
