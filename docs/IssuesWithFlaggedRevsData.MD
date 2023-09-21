# Untracked resources

After the update, some customers may experience issues with the previously approved pages that now show changes in
resources.

This can have two causes:
- Resources were actually not approved before the page that includes it (correct behaviour), which didnt seem to be
reported by FlaggedRevs, appearing as a change in behaviour
- FlaggedRevs failed to keep track of all included resources, leading to, after migration, some resources being untracked
which ContentStabilization will report as "resources out of sync"

# Diagnosing issues
- Open a page which has seemly wrong "changes in resources" state by adding `debug=true` to the URL.
- Check `outOfSyncInclusions` section, to see if resources should actually be out of sync or not
- Check `outOfSyncResources.untracked` section, to check if there are any resources that are untracked

# Fixing the problems

We have a script that will re-sync resources, without modifying any existing stable points. It can fix "untracked" as
well as actual sync differences.

- Create a file with a newline-separated list of resources that need to be synced. Use prefixed names of the page, in
english or the content language. Keep in mind, this should be a list of RESOURCES, not pages that include those resources.
  - For files, specify file pages (e.g. `File:Example.jpg`). Script will understand that it's a file inclusion and
  adjust properly
- Script will go through all pages in the wiki, finding where those resources appear out of sync and re-sync them
- If you want to re-sync only pages in a particular namespace, specify the namespace index in `--namespace` param

List of resources example, `resources.txt`:
```text
Template:ABC
Template:XYZ
File:Example.jpg
```

Re-syncing only untracked resources in NS_MAIN
```bash
php extensions/ContentStabilization/maintenance/syncResources.php --namespace=0 --untracked-only resources.txt
```

Re-syncing all resources that are out of sync (tracked and untracked), in all namespaces where CS is enabled
```bash
php extensions/ContentStabilization/maintenance/syncResources.php resources.txt
```
