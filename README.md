# IF Market

Internal system for Italiano Fácil to manage the IF Market, IF Coins, products, stock, redemptions, donations, coin requests, redemption adjustments, and automatic daily goal rewards.

The system was created to make internal rewards more fun, organized, and easy to manage for the team.

---

## Overview

The IF Market uses an internal currency called IF Coin.

Each team member has:

- an individual account;
- a unique access code;
- an IF Coins balance;
- an assigned department;
- a history of redemptions, donations, requests, and rewards.

With IF Coins, team members can redeem products available in the IF Market.

---

## User Profiles

The system has two types of access:

### User

Users can:

- access their own account;
- view their IF Coins balance;
- view their personal access code;
- redeem available products;
- donate IF Coins to other team members;
- request IF Coins from other team members;
- adjust a redemption when they take a different product.

### Administrator

Administrators can:

- access the admin panel;
- create and edit users;
- manage products and stock;
- adjust IF Coins balances;
- view redemptions, donations, requests, and rewards history;
- delete users permanently;
- manage the IF Market operation.

The administrator profile only grants access to the admin panel.

It does not automatically define the user’s department or monthly coin amount.

---

## IF Coins

IF Coins are the internal currency of the IF Market.

Each product costs 1 IF Coin.

Team members can receive IF Coins through:

- monthly automatic recharge;
- automatic daily sales goal reward;
- manual adjustments made by administrators;
- donations from other team members.

---

## Monthly Automatic Recharge

On the 1st day of each month, the system automatically recharges user balances based on their department.

Current rules:

- Leadership: 10 IF Coins
- Intern/Apprentice: 2 IF Coins
- Sales: 0 IF Coins
- All other departments: 5 IF Coins

The system has a monthly lock to prevent the recharge from running more than once in the same month.

---

## Departments

Available departments:

- Sales
- Support
- Technology
- Marketing
- Care and Well-being
- HR
- Finance
- Intern/Apprentice
- Administrative
- Leadership

The department defines how many IF Coins the person receives during the monthly recharge.

---

## Products and Stock

Administrators can manage all products available in the IF Market.

Each product has:

- name;
- description;
- image;
- stock quantity;
- cost in IF Coins;
- active or inactive status.

Administrators can:

- create products;
- edit product information;
- update stock;
- upload or change product images;
- remove products from the storefront;
- monitor available stock.

---

## Product Redemption

A user enters the IF Market using their personal code, selects an available product, and confirms the redemption.

When a product is redeemed, the system:

- deducts 1 IF Coin from the user’s balance;
- deducts 1 unit from the product stock;
- records the redemption in the history;
- shows a confirmation message.

---

## Redemption Adjustment

The Adjust Redemption feature was created for cases where a user redeems one product in the system, but when taking the item, they end up taking another available product instead.

Example:

The user redeemed a Monster, but when they went to take it, there was no Monster available, so they took a Baly instead.

In this case, the user can return to the system and adjust the redemption to the product they actually took.

The system will:

- update the redemption history;
- deduct the stock of the product that was actually taken.

This feature should only be used when the user actually takes another item instead of the product originally redeemed.

---

## IF Coin Donations

Users can donate IF Coins to other team members using their access code.

When a donation is made, the system:

- validates the recipient code;
- prevents donations to oneself;
- checks if the donor has enough balance;
- deducts IF Coins from the donor;
- adds IF Coins to the recipient;
- records the donation;
- sends an email notification to the recipient.

---

## IF Coin Requests

The system also includes a Request Coin feature.

A user can request 1 IF Coin from another team member in a fun and lighthearted way.

A coin request includes:

- the recipient’s access code;
- a custom message;
- a sticker or image.

When a request is sent, the system:

- validates the recipient code;
- prevents requests to oneself;
- checks if the recipient is active;
- checks if the recipient has balance available;
- sends an email with the request;
- includes a button to donate 1 IF Coin;
- opens the donation form already filled in for confirmation.

The donation does not happen automatically.

The person receiving the request must confirm the donation.

---

## Coin Request Limits

To keep the game organized, the system has request limits.

Current rules:

- each user can make up to 3 IF Coin requests per day;
- each request is for 1 IF Coin;
- users cannot request coins from themselves;
- users cannot request coins from inactive users;
- users cannot request coins from someone with no balance.

---

## Automatic Daily Goal Reward

The system integrates with the sales dashboard to automatically reward salespeople who hit their daily sales goal.

When an authorized salesperson reaches the daily goal, the system adds:

- +1 IF Coin

The reward is recorded in the history as:

META DIÁRIA BATIDA - RECARGA AUTOMÁTICA

The automatic reward has a daily lock, which means:

- each salesperson can receive this reward only once per day;
- opening the panel multiple times will not duplicate the reward;
- on the next day, the salesperson can receive the reward again if the goal is reached.

---

## Salespeople With Automatic Goal Reward

The automatic daily goal reward applies only to the salespeople defined in the system.

Current list:

- Gabriel Martins
- Heloisa
- Marina
- Alana
- Victoria
- Bruna

Kelvyn is not included in the automatic reward rule.

People outside this list do not receive automatic IF Coins from the daily sales goal.

---

## Sales Dashboard Integration

The system checks the sales dashboard endpoint to verify the daily sales results.

Endpoint used:

https://n8n.italianofacil.com.br/webhook/dashboard-vendas-resumo

The system reads the daily sales data and compares it with each salesperson’s daily goal.

If the dashboard does not respond or the data is incomplete, the system does not reward anyone automatically.

This prevents incorrect rewards.

---

## History

The system records important actions, such as:

- product redemptions;
- redemption adjustments;
- IF Coin donations;
- IF Coin requests;
- automatic goal rewards;
- manual balance adjustments;
- monthly recharges.

Administrators can view the full movement history in the admin panel.

---

## Automatic Emails

The system sends automatic emails for important actions, including:

- user creation;
- IF Coins received;
- daily goal reached;
- donation received;
- IF Coin request received;
- internal reward notifications.

The emails follow the IF Market visual identity and include custom layouts, images, and friendly messages.

---

## Email Configuration

SMTP configuration is stored in:

data/mail_config.php

This file contains sensitive email access information, such as:

- SMTP host;
- port;
- username;
- password;
- sender email;
- sender name.

Important:

Never upload mail_config.php to public repositories.

---

## Database

The system uses SQLite.

Main database file:

data/mercadinho.sqlite

Main tables:

- users
- products
- transactions
- credit_logs
- donation_logs
- coin_requests
- app_settings

---

## File Structure

Main project structure:

mercadinho/
├── index.php
├── painel.php
├── assets/
│   ├── styles.css
│   └── app.js
├── data/
│   ├── mercadinho.sqlite
│   └── mail_config.php
└── uploads/

---

## Main Files

### index.php

Responsible for the public IF Market storefront.

Includes:

- access code validation;
- available product listing;
- product redemption;
- balance update;
- stock update.

### painel.php

Responsible for the admin panel and user account area.

Includes:

- login;
- user account;
- admin panel;
- user management;
- product management;
- IF Coin donations;
- IF Coin requests;
- redemption adjustments;
- automatic monthly recharge;
- automatic daily goal reward;
- system history.

### assets/styles.css

Responsible for the system design.

Includes:

- premium visual layout;
- responsive interface;
- cards;
- buttons;
- forms;
- tables;
- mobile navigation;
- admin panel styling.

### assets/app.js

Responsible for front-end interactions.

Includes:

- confirmation alerts;
- mobile menu behavior;
- interface effects;
- user experience details.

---

## Initial Administrator

When the database is created for the first time, the system creates a default administrator.

Initial credentials:

Email: admin@italianofacil.com
Password: admin123

It is recommended to change the administrator password after installation.

---

## Security

The system includes basic security measures, such as:

- password hashing;
- CSRF validation in forms;
- prepared statements for database queries;
- corporate email validation;
- balance validation before donations and redemptions;
- prevention of self-donation;
- prevention of self-deletion;
- active and inactive user control;
- daily locks to avoid duplicated rewards;
- monthly lock to avoid duplicated recharges.

---

## User Deletion

Administrators can permanently delete users.

When a user is deleted, the system also removes related records, such as:

- donations;
- IF Coin requests;
- balance adjustments;
- redemptions.

This releases the email so it can be registered again.

---

## Backup

Before making major changes, it is recommended to back up:

- data/mercadinho.sqlite
- data/mail_config.php
- uploads/

These files preserve:

- users;
- products;
- stock;
- history;
- uploaded images;
- email settings.

---

## Important Rules

- Every product costs 1 IF Coin.
- Monthly recharge happens on the 1st day of the month.
- Sales department receives 0 IF Coins monthly.
- Automatic daily goal reward applies only to authorized salespeople.
- Each salesperson can receive a maximum of 1 automatic goal reward per day.
- IF Coin requests do not trigger automatic donations.
- Redemption adjustment should only be used when another product is actually taken instead of the original redeemed product.
- Stock should be reviewed regularly to keep the system aligned with the physical products.

---

## Purpose

The IF Market was created to turn small internal rewards into a more fun, organized, and engaging experience for the Italiano Fácil team.

Besides controlling products, stock, and balances, the system helps recognize achievements, celebrate daily goals, encourage interaction between team members, and make the work routine more enjoyable.
