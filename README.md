# Money_point
Money Point is a lightweight mobile money float management system for Tanzanian agents. Track Till, Lipa Namba, Cash and daily reports across 4 lines (Vodacom, Airtel, Tigo, Halotel). Built with PHP, MySQL and Bootstrap 5. Simple, fast and secure.
# Money Point — Mobile Money Float Management System

Money Point is a web-based application developed to help mobile money agents in Tanzania manage their daily float and cash balances. It provides a central place to record opening and closing balances, monitor transactions across mobile money lines, and review daily financial records.

The system is built with PHP, MySQL/MariaDB, HTML, CSS, JavaScript, and Bootstrap. It is designed to run on a local web server such as XAMPP, WAMP, or Laragon.

## Features

### 1. User Authentication

* Login using a username or email address.
* Password hashing for secure credential storage.
* Session-based authentication.
* Session regeneration after login.

### 2. Dashboard

* Displays the current working day's status.
* Shows total Till, Lipa Namba, float, and cash balances.
* Calculates the total money available across float and cash.
* Provides an overview of registered mobile money lines.
* Displays the latest daily closing record.

### 3. Mobile Money Lines

* View active mobile money lines.
* Register additional lines.
* Select a network for each line.
* Organise Till and Lipa Namba balances by line.

### 4. Opening a Business Day

* Record the opening Till and Lipa Namba balances.
* Enter the opening cash balance.
* Calculate opening totals automatically.
* Preview the figures before saving.
* Prevent a second opening for the same working day.

### 5. Closing a Business Day

* Record closing balances for each line.
* Enter the closing cash balance.
* Review the calculated totals before confirmation.
* Mark the working day as closed.
* Prevent further changes to a closed day's balances through the application.

### 6. Daily History

* View previous daily records.
* Filter records by date range.
* Filter by opening or closing status.
* Quickly access records for today, yesterday, the last seven days, or the current month.
* View summary figures for the selected period.

### 7. Financial Reports

The reporting section provides:

* Daily reports.
* Weekly reports.
* Monthly reports.
* Float reports.
* Cash reports.
* Balance summaries by mobile money line.
* Daily breakdowns.
* Browser-based printing and PDF export.

## Technology Stack

| Component         | Technology              |
| ----------------- | ----------------------- |
| Backend           | PHP 8.2                 |
| Database          | MySQL / MariaDB         |
| Frontend          | HTML5, CSS3, JavaScript |
| CSS Framework     | Bootstrap 5.3           |
| Icons             | Bootstrap Icons         |
| Web Server        | Apache                  |
| Local Development | XAMPP, WAMP, or Laragon |

## Project Structure

The main application files are organised as follows:

```text
money_point/
├── connection.php
├── login.php
├── logout.php
├── money/
│   ├── dashboard.php
│   ├── lines.php
│   ├── open-day.php
│   ├── close-day.php
│   ├── history.php
│   ├── reports.php
│   └── settings.php
└── database.sql
```

The structure above describes the main application files. Additional files may be present in the working project.

## Database

The application uses MySQL or MariaDB to store user accounts, mobile money lines, daily balances, cash records, and activity logs.

The database contains the following tables:

| Table            | Purpose                                                  |
| ---------------- | -------------------------------------------------------- |
| `users`          | Stores user account information.                         |
| `mobile_lines`   | Stores registered mobile money lines and their networks. |
| `daily_closing`  | Stores daily totals and opening and closing status.      |
| `daily_balances` | Stores opening and closing balances for each line.       |
| `daily_cash`     | Stores opening and closing cash balances.                |
| `audit_logs`     | Records application activities.                          |

The database schema is provided in `database.sql`.

## Installation

### Requirements

Before installing the application, make sure you have:

* PHP 8.0 or later.
* MySQL 5.7 or later, or MariaDB 10.3 or later.
* Apache through XAMPP, WAMP, or Laragon.
* A web browser.

### Step 1: Download the Project

Clone the repository using its actual GitHub URL:

```bash
git clone YOUR_REPOSITORY_URL
```

Alternatively, download the repository as a ZIP file and extract it.

### Step 2: Move the Project

Place the project folder in your local web server directory.

For XAMPP:

```text
C:\xampp\htdocs\money_point
```

For WAMP:

```text
C:\wamp64\www\money_point
```

For Laragon:

```text
C:\laragon\www\money_point
```

### Step 3: Create the Database

Start Apache and MySQL from your local server control panel.

Open phpMyAdmin in your browser and create a database named:

```text
mobile_money_db
```

### Step 4: Import the Database

Select `mobile_money_db` in phpMyAdmin, open the **Import** tab, and select the project's `database.sql` file.

Start the import and wait for confirmation that it has completed successfully.

### Step 5: Configure the Database Connection

Open `connection.php` and update the connection settings to match your local MySQL or MariaDB configuration.

For a typical local XAMPP installation, the settings may look like this:

```php
$host = 'localhost';
$dbname = 'mobile_money_db';
$username = 'root';
$password = '';
```

These values are examples. Use the credentials configured on your computer.

### Step 6: Open the Application

Start Apache and MySQL, then open:

```text
http://localhost/money_point/login.php
```

If the application is installed in a different folder, update the URL accordingly.

## Daily Operating Procedure

The application follows a simple daily workflow:

1. Log in to the system.
2. Check the current day's status on the dashboard.
3. Open the business day by entering the initial balances.
4. Record the relevant balances for each mobile money line.
5. Enter the closing balances and cash at the end of the day.
6. Review and confirm the closing figures.
7. Use the history and reports sections to review previous records.
8. Log out when finished.

## Balance Calculations

The application uses the following calculations to summarise the recorded balances:

**Total Till**

The sum of the Till balances across all registered lines.

**Total Lipa Namba**

The sum of the Lipa Namba balances across all registered lines.

**Total Float**

Total Float = Total Till + Total Lipa Namba

**Total Money**

Total Money = Total Float + Cash

The dashboard and reports use these totals to provide an overview of the recorded balances.

## Daily Record Rules

The intended daily workflow is based on one opening and one closing per working day.

* Each working day should have one opening record.
* A working day should be closed after the closing balances have been entered.
* Closed records should not be changed through the normal application workflow.
* Balance updates should only be allowed while the day is open.

These rules should also be enforced by the application's server-side logic and database constraints where appropriate.

## Security

The application includes several security measures:

* Password hashing.
* Session-based authentication.
* Prepared statements for database queries.
* Output escaping to reduce cross-site scripting risks.
* Session regeneration after login.
* Activity logging.

For deployment beyond a local development environment, configure appropriate database permissions, protect sensitive configuration files, and use HTTPS.

Do not publish real user credentials, API keys, or sensitive financial records in the repository.

## Future Improvements

Possible improvements for future versions include:

* Completing the settings page.
* Adding user profile management.
* Supporting server-side PDF generation.
* Adding Excel report exports.
* Introducing SMS notifications.
* Expanding multi-user support.
* Adding a light and dark theme.
* Developing a mobile application.

These are planned improvements and may not be available in the current version.

## Contributing

Contributions and suggestions are welcome.

To contribute:

1. Fork the repository.
2. Create a branch for your changes.
3. Make and test your changes.
4. Commit your work with a clear commit message.
5. Push the branch to your fork.
6. Open a pull request describing the changes.

## Author

**Robert Meshack**

GitHub: https://github.com/robertmeshack39-creator

## License

Add a `LICENSE` file to the repository to specify the terms under which the project may be used, modified, and distributed.

---

Money Point is a project focused on making daily mobile money float and cash management easier to record, review, and organise.
