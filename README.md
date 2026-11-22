# CarbonTrack Logistics — Carbon Emissions & Shipment Tracking Web App

A web-based logistics and carbon footprint tracking application built to monitor shipment emissions, track company carbon budgets, and insert new customer and shipment records connected to a relational database.

## Tech Stack
- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP
- **Database:** MySQL (Relational Schema)

## Key Features
- **Live Metrics Dashboard:** Displays real-time summary cards for total $\text{CO}_2$ emitted ($\text{kg}$), total shipments logged, active tracked customers, and budget breaches.
- **Customer & Budget Tracking Table:** Lists organizations with industry tags, country/contact details, joined dates, and allocated $\text{CO}_2$ budgets.
- **Data Insertion Workflows:** Includes dedicated forms to add new customers (with monthly $\text{CO}_2$ budgets) and log new shipments (customer selection, origin/destination cities, cargo weight, cargo type, dates, and priority level).
- **Multi-Section Navigation:** Structured navigation across Dashboard, Shipments, Emissions, Fleet, Goals, Alerts, and Manage views.

## Screenshots

### Overview Dashboard & KPI Cards
<img width="1316" height="556" alt="image" src="https://github.com/user-attachments/assets/f252b7bf-3fe5-419d-9137-b24cfc4abe9a" />


### Tracked Customers & $\text{CO}_2$ Budgets Table
<img width="1325" height="559" alt="image" src="https://github.com/user-attachments/assets/5ae22d8a-8d01-40a7-a87c-548e46aafe95" />


### Add New Customer & Shipment Forms
<img width="1295" height="551" alt="image" src="https://github.com/user-attachments/assets/151a1217-3053-47a0-9360-ae0f0831d156" />


## Local Setup (XAMPP)
1. Place the project folder inside `C:\xampp\htdocs\carbontrack-logistics`.
2. Start **Apache** and **MySQL** in XAMPP Control Panel.
3. Create the database in `phpMyAdmin` and import the project's `.sql` file.
4. Configure your database connection settings and open the app in your browser.
