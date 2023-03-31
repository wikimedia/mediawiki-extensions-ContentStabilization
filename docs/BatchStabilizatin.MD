# Batch stabilization

In case of the need to stabilize a large number of pages, it is possible to use the
`batchStabilize.php` script. It is located in the `maintenance` directory of the extension.

## Usage

### Specifying pages

Manually list pages:

	php batchStabilize.php --pages="Page1|Page2|Page3"

Specify a namespace:

	php batchStabilize.php --namespace={namespaceIndex or name}

### Specifying user

Specify user to act as the "approver".
Make sure the user has the `contentstabilization-stabilize` right (usually a `sysop`)

	php batchStabilize.php --user={username}

If no user is specified, generic `StabilizationBot` will be used

### Specifying comment

Specify a comment to be used for the stabilization log entry

	php batchStabilize.php --comment={comment}

If no comment is specified, a generic one will be used

### Output level

By default, script will only output how many pages it found, how many were successfully stabilized, and how many failed.
If you want more info, use the `--verbose` flag

	php batchStabilize.php --verbose

This will output reason for failure on each page that failed to stabilize
