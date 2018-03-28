# wp-privacy-requests
Temporary plugin to develop the administrative UI for core WordPress privacy requests

## To use
 - First, set up the test environment ( assuming wordpress-svn working copy in `~/sites/localhost/wordpress-svn`) by doing the following:

```
# Revert all changes
cd ~/sites/localhost/wordpress-svn
svn revert -R .
svn up

# Apply latest patch from https://core.trac.wordpress.org/ticket/43443
# This adds the latest version of the email confirmation code
cd src
patch -p0 < 43443.4.diff

cd ~/sites/localhost/wordpress-svn/src/wp-content/plugins

# Clone the temporary plugin if you haven’t already
git clone git@github.com:allendav/wp-privacy-requests.git
```

- Then...
- Navigate to wp-admin > Tools > Personal Data Requests
- Select Personal Data Export if needed
- Enter an email address of a user with comments on the site
- Hit Send request
- Note the request should now appear in the table
- Hover over the email address in the table, then scroll down and click on Download Personal Data
- Observe the personal data in the Javascript console
- Bonus points: Install and activate the WP Mail SMTP by WPForms plugin so you can actually send yourself confirmation emails from your local WordPress install
