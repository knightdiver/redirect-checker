# FM Dev's Redirect Checker

## Overview
The **Redirect Checker** is a PHP-based tool designed to verify website redirects. By uploading a list of URLs, the tool validates whether they redirect to the expected destination and provides a detailed report.

## Features
- **Bulk Redirect Validation**: Upload a `.csv`, `.txt`, or `.conf` file to validate redirects.
- **Multiple File Format Support**:
  - `.csv`: Old URL → Expected Redirect URL mapping.
  - `.txt` / `.conf`: Nginx rewrite rules.
- **Uses `get_headers()` for Redirect Checks**: Extracts HTTP headers to determine if a redirect exists.
- **Detailed Status Reports**: Categorizes redirects as success, mismatched, failed, or domain mismatches.
- **Simple Web Interface**: Upload a file and view results instantly.

## Requirements
- A web server with **PHP 7+**.
- `allow_url_fopen` must be enabled in `php.ini` (for `get_headers()` to work).

## Installation
1. Clone or download the repository.
2. Place the files in a directory on your web server.
3. Ensure your server supports PHP and has `allow_url_fopen` enabled.
4. Open `index.php` in a web browser.

## Usage
### 1. Prepare Your Input File
The tool supports the following file formats:

#### **CSV Format**
A CSV file must contain at least **two columns**:
- **Column 1**: Old URL
- **Column 2**: Expected Redirect URL

##### Example CSV File:
```
http://example.com/old-page,https://example.com/new-page
https://olddomain.com/old-product,https://newdomain.com/new-product
/old-route,https://newdomain.com/new-route
```
- The tool will strip the domain from the first column if necessary.

#### **Nginx Rewrite Rules**
If using an Nginx rewrite configuration file (`.txt` or `.conf`), it should contain rules like this:
```
rewrite ^/old-page$ https://example.com/new-page permanent;
rewrite ^/blog/post$ /new-post permanent;
```
**Note:** Currently, the tool does not process Nginx `301`/`302` directives directly.

### 2. Upload & Validate
1. Open `index.php` in a browser.
2. Select and upload your redirect file (`.csv`, `.txt`, or `.conf`).
3. Choose HTTP or HTTPS for testing.
4. Click **Check Redirects**.
5. View the results in the table.

## Redirect Statuses
| Status | Meaning |
|--------|---------|
| ✅ Success | The redirect matches the expected URL. |
| ❓ Domain Mismatch | The domains differ between the redirected and expected URL. |
| ⚠️ Mismatch | The redirected URL does not match the expected destination. |
| ❌ Failed | No redirect was found for the given URL. |

## Technical Details
- Uses `get_headers()` to retrieve HTTP headers and check for `Location` redirects.
- Extracts the final redirected URL for comparison.
- Strips unnecessary whitespace and ensures clean URL comparisons.
- Supports validation for **Nginx rewrite rules and CSV-based URL mappings**.

## Contributing
Contributions are welcome! Feel free to submit a pull request or open an issue.

## License
This project is licensed under the [MIT License](LICENSE).
