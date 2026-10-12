# Bincom PHP/MySQL Developer Assessment

## Overview

This project contains my solutions to the Bincom PHP/MySQL Developer assessment. It uses PHP and MySQL to retrieve, display, and manage polling unit election data.

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- MySQLi for database connectivity

## Project Structure


bincom-test/
├── index.php
├── database.php
├── question1.php
├── question2.php
├── question3.php
└── README.md


## Assessment Solutions

- **Question 1:** Displays polling unit election results for a selected polling unit.
- **Question 2:** Displays the total number of votes scored by each political party in a selected Local Government Area (LGA).
- **Question 3:** Provides a form to create a new polling unit and enter election results for political parties.

The `index.php` file serves as the landing page, providing links to the three assessment solutions.

## Database Setup

The project uses a MySQL database containing tables for states, Local Government Areas, wards, polling units, political parties, and announced election results.

To run the project locally:

1. Install XAMPP or another PHP/MySQL development environment.
2. Start Apache and MySQL.
3. Import the supplied assessment database into phpMyAdmin.
4. Update the database connection details in `database.php`.
5. Place the project folder inside your web server's document root.
6. Open the project through your local server.

## Live Demo

[View the deployed assessment](http://avyafundraiser.ifree.page/electionapp/)

## Author

**Chiemeka Evans Izuchukwu**

GitHub: [SwitzerEvans](https://github.com/SwitzerEvans)