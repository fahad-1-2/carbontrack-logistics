-- ============================================================
--        CARBON FOOTPRINT LOGISTICS TRACKER
--        A Pure SQL Database Project
--        Tracks CO2 emissions generated during
--        the movement of goods across supply chains.
--
--        Formula Used:
--        CO2 (kg) = Distance (km) x Weight (kg) x Emission_Factor
--
--        Compatible With: MySQL 8.0+
-- ============================================================


-- ============================================================
-- PHASE 1: DATABASE SETUP
--          Create and select the project database
-- ============================================================

-- Drop the database if it already exists (fresh start)
                                DROP DATABASE IF EXISTS carbon_logistics;

-- Create our main project database
 CREATE DATABASE carbon_logistics;

-- Switch into our database
USE carbon_logistics;


-- ============================================================
-- PHASE 2: CREATE ALL TABLES (Schema Design)
--          We build all 11 tables here with proper
--          Primary Keys, Foreign Keys, and Constraints.
--          Order matters -- parent tables come before children.
-- ============================================================


-- ------------------------------------------------------------
-- TABLE 1: customers
-- Stores the companies or individuals sending shipments.
-- Each customer has a monthly carbon budget they must stay under.
-- ------------------------------------------------------------
CREATE TABLE customers (
    customer_id        INT AUTO_INCREMENT PRIMARY KEY,
    company_name       VARCHAR(100)  NOT NULL,
    country            VARCHAR(60)   NOT NULL,
    industry_type      VARCHAR(60)   NOT NULL,               -- e.g. Retail, Manufacturing, Food
    contact_email      VARCHAR(100)  UNIQUE NOT NULL,
    carbon_budget_kg   DECIMAL(10,2) NOT NULL DEFAULT 1000,  -- monthly CO2 limit in kg
    joined_date        DATE          NOT NULL
);


-- ------------------------------------------------------------
-- TABLE 2: locations
-- Stores every origin/destination point (cities, ports, airports).
-- Normalized so we never repeat city data across routes.
-- ------------------------------------------------------------
CREATE TABLE locations (
    location_id    INT AUTO_INCREMENT PRIMARY KEY,
    city           VARCHAR(80)  NOT NULL,
    country        VARCHAR(60)  NOT NULL,
    region         VARCHAR(60),                              -- e.g. South Asia, Europe
    location_type  VARCHAR(40)  NOT NULL,                   -- Warehouse / Port / Airport / Hub
    latitude       DECIMAL(9,6),
    longitude      DECIMAL(9,6)
);


-- ------------------------------------------------------------
-- TABLE 3: vehicles
-- Every transport asset available in the logistics fleet.
-- A vehicle can be a Plane, Truck, Van, or Ship.
-- ------------------------------------------------------------
CREATE TABLE vehicles (
    vehicle_id       INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_name     VARCHAR(80)  NOT NULL,
    vehicle_type     VARCHAR(40)  NOT NULL,                  -- Plane / Truck / Van / Ship
    fuel_type        VARCHAR(40)  NOT NULL,                  -- Diesel / Petrol / Electric / Jet-A
    max_payload_kg   DECIMAL(10,2) NOT NULL,                 -- maximum weight it can carry
    year_manufactured INT,
    is_active        BOOLEAN      NOT NULL DEFAULT TRUE,     -- is the vehicle currently in use
    CHECK (max_payload_kg > 0)
);


-- ------------------------------------------------------------
-- TABLE 4: emission_rates
-- Master lookup table. Stores how much CO2 each vehicle type
-- produces per km per kg of cargo carried.
-- valid_from_date lets us track rate changes over time.
-- ------------------------------------------------------------
CREATE TABLE emission_rates (
    rate_id             INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_type        VARCHAR(40)   NOT NULL,              -- links logically to vehicles.vehicle_type
    fuel_type           VARCHAR(40)   NOT NULL,
    co2_per_km_per_kg   DECIMAL(10,6) NOT NULL,              -- kg of CO2 per km per kg of cargo
    data_source         VARCHAR(80)   DEFAULT 'IPCC',        -- e.g. IPCC, EPA, EU
    valid_from_date     DATE          NOT NULL,
    CHECK (co2_per_km_per_kg > 0)
);


-- ------------------------------------------------------------
-- TABLE 5: shipments
-- The main fact table. One row = one shipment order.
-- A shipment travels from an origin to a destination,
-- and may be broken into multiple route legs.
-- ------------------------------------------------------------
CREATE TABLE shipments (
    shipment_id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id          INT           NOT NULL,
    origin_location_id   INT           NOT NULL,
    dest_location_id     INT           NOT NULL,
    total_weight_kg      DECIMAL(10,2) NOT NULL,
    cargo_type           VARCHAR(60)   NOT NULL,             -- Electronics / Food / Chemicals / General
    shipment_date        DATE          NOT NULL,
    delivery_deadline    DATE,
    status               VARCHAR(20)   NOT NULL DEFAULT 'Pending', -- Pending / In-Transit / Delivered
    priority_level       VARCHAR(20)   NOT NULL DEFAULT 'Standard', -- Standard / Express / Critical

    -- Foreign Keys
    FOREIGN KEY (customer_id)        REFERENCES customers(customer_id)  ON DELETE CASCADE,
    FOREIGN KEY (origin_location_id) REFERENCES locations(location_id),
    FOREIGN KEY (dest_location_id)   REFERENCES locations(location_id),

    -- Constraints
    CHECK (total_weight_kg > 0),
    CHECK (status IN ('Pending', 'In-Transit', 'Delivered', 'Cancelled')),
    CHECK (priority_level IN ('Standard', 'Express', 'Critical'))
);


-- ------------------------------------------------------------
-- TABLE 6: routes
-- A shipment can travel in multiple legs.
-- e.g. Leg 1: Van → Airport | Leg 2: Plane → Port | Leg 3: Truck → Warehouse
-- Each leg has its own vehicle, distance, and weight.
-- ------------------------------------------------------------
CREATE TABLE routes (
    route_id             INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id          INT           NOT NULL,
    leg_number           INT           NOT NULL,             -- 1, 2, 3... (order of the leg)
    origin_location_id   INT           NOT NULL,
    dest_location_id     INT           NOT NULL,
    vehicle_id           INT           NOT NULL,
    distance_km          DECIMAL(10,2) NOT NULL,
    actual_weight_kg     DECIMAL(10,2) NOT NULL,             -- weight carried on this specific leg
    departure_time       DATETIME,
    arrival_time         DATETIME,

    -- Foreign Keys
    FOREIGN KEY (shipment_id)          REFERENCES shipments(shipment_id) ON DELETE CASCADE,
    FOREIGN KEY (origin_location_id)   REFERENCES locations(location_id),
    FOREIGN KEY (dest_location_id)     REFERENCES locations(location_id),
    FOREIGN KEY (vehicle_id)           REFERENCES vehicles(vehicle_id),

    CHECK (distance_km > 0),
    CHECK (actual_weight_kg > 0),
    CHECK (leg_number > 0)
);


-- ------------------------------------------------------------
-- TABLE 7: emission_logs
-- The calculated CO2 for every single route leg.
-- This is where the formula is applied and results are stored.
-- co2_emitted_kg = distance_km x weight_kg x co2_per_km_per_kg
-- ------------------------------------------------------------
CREATE TABLE emission_logs (
    log_id           INT AUTO_INCREMENT PRIMARY KEY,
    route_id         INT           NOT NULL UNIQUE,          -- one log per route leg
    shipment_id      INT           NOT NULL,
    vehicle_id       INT           NOT NULL,
    rate_id          INT           NOT NULL,
    distance_km      DECIMAL(10,2) NOT NULL,
    weight_kg        DECIMAL(10,2) NOT NULL,
    co2_emitted_kg   DECIMAL(10,4) NOT NULL,                 -- the final calculated result
    calculated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (route_id)    REFERENCES routes(route_id)         ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(shipment_id)   ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id)  REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (rate_id)     REFERENCES emission_rates(rate_id),

    CHECK (co2_emitted_kg >= 0)
);


-- ------------------------------------------------------------
-- TABLE 8: carbon_goals
-- Monthly CO2 reduction targets set per customer.
-- Helps measure if customers are improving over time.
-- ------------------------------------------------------------
CREATE TABLE carbon_goals (
    goal_id       INT AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT          NOT NULL,
    target_month  INT          NOT NULL,                    -- 1 = January, 12 = December
    target_year   INT          NOT NULL,
    max_co2_kg    DECIMAL(10,2) NOT NULL,                  -- the CO2 limit for that month
    goal_type     VARCHAR(40)  NOT NULL DEFAULT 'Monthly-Total', -- Monthly-Total / Per-Shipment

    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    CHECK (target_month BETWEEN 1 AND 12),
    CHECK (max_co2_kg > 0)
);


-- ------------------------------------------------------------
-- TABLE 9: goal_performance
-- Records actual vs target CO2 at the end of each month.
-- Tells us if a customer MET or EXCEEDED their carbon goal.
-- ------------------------------------------------------------
CREATE TABLE goal_performance (
    performance_id  INT AUTO_INCREMENT PRIMARY KEY,
    goal_id         INT           NOT NULL,
    actual_co2_kg   DECIMAL(10,4) NOT NULL,
    variance_kg     DECIMAL(10,4),                          -- actual - target (negative = under budget = good)
    met_goal        VARCHAR(3)    NOT NULL DEFAULT 'NO',    -- YES or NO
    recorded_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (goal_id) REFERENCES carbon_goals(goal_id) ON DELETE CASCADE,
    CHECK (met_goal IN ('YES', 'NO'))
);


-- ------------------------------------------------------------
-- TABLE 10: alerts
-- Auto-triggered when a shipment or customer exceeds
-- their carbon budget. Acts as the warning system.
-- ------------------------------------------------------------
CREATE TABLE alerts (
    alert_id     INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id  INT          ,
    customer_id  INT          NOT NULL,
    alert_type   VARCHAR(50)  NOT NULL,                    -- OverBudget / HighEmission / DeadlineMiss
    message      TEXT         NOT NULL,
    triggered_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_resolved  BOOLEAN      NOT NULL DEFAULT FALSE,

    FOREIGN KEY (shipment_id)  REFERENCES shipments(shipment_id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id)  REFERENCES customers(customer_id) ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- TABLE 11: fuel_prices
-- Tracks fuel cost over time per region.
-- Useful for correlating emission costs with money spent.
-- ------------------------------------------------------------
CREATE TABLE fuel_prices (
    price_id        INT AUTO_INCREMENT PRIMARY KEY,
    fuel_type       VARCHAR(40)   NOT NULL,
    price_per_litre DECIMAL(8,4)  NOT NULL,
    currency        VARCHAR(10)   NOT NULL DEFAULT 'USD',
    recorded_date   DATE          NOT NULL,
    region          VARCHAR(60)   NOT NULL,

    CHECK (price_per_litre > 0)
);


-- ============================================================
-- PHASE 3: SEED DATA — INSERT all lookup/reference data
--          This is the foundational data the system needs
--          before any shipments can be logged.
--          Order: customers → locations → vehicles →
--                 emission_rates → fuel_prices
-- ============================================================


-- ------------------------------------------------------------
-- INSERT: customers
-- 6 companies from different industries and countries
-- ------------------------------------------------------------
INSERT INTO customers (company_name, country, industry_type, contact_email, carbon_budget_kg, joined_date) VALUES
('SwiftCargo Ltd',       'Pakistan',      'Logistics',     'info@swiftcargo.pk',    800.00,  '2023-01-15'),
('GreenGoods Inc',       'Germany',       'Retail',        'ops@greengoods.de',     600.00,  '2023-03-22'),
('TechShip Solutions',   'USA',           'Electronics',   'ship@techship.com',     1200.00, '2023-05-10'),
('FreshRoute Foods',     'Netherlands',   'Food',          'logistics@freshroute.nl',500.00,  '2023-07-01'),
('HeavyHaul Corp',       'China',         'Manufacturing', 'haul@heavyhaul.cn',     2000.00, '2022-11-20'),
('EcoDrop Deliveries',   'UK',            'E-Commerce',    'hello@ecodrop.co.uk',   400.00,  '2024-01-05');


-- ------------------------------------------------------------
-- INSERT: locations
-- 10 real-world logistics hubs (warehouses, airports, ports)
-- ------------------------------------------------------------
INSERT INTO locations (city, country, region, location_type, latitude, longitude) VALUES
('Karachi',      'Pakistan',     'South Asia',    'Port',      24.8607,  67.0011),
('Lahore',       'Pakistan',     'South Asia',    'Warehouse', 31.5204,  74.3587),
('Dubai',        'UAE',          'Middle East',   'Airport',   25.2532,  55.3657),
('Frankfurt',    'Germany',      'Europe',        'Airport',   50.0379,   8.5622),
('Shanghai',     'China',        'East Asia',     'Port',      31.2304, 121.4737),
('Rotterdam',    'Netherlands',  'Europe',        'Port',      51.9225,   4.4792),
('London',       'UK',           'Europe',        'Hub',       51.5074,  -0.1278),
('New York',     'USA',          'North America', 'Warehouse', 40.7128, -74.0060),
('Singapore',    'Singapore',    'Southeast Asia','Port',       1.3521, 103.8198),
('Istanbul',     'Turkey',       'Middle East',   'Hub',       41.0082,  28.9784);


-- ------------------------------------------------------------
-- INSERT: vehicles
-- 8 vehicles covering all major transport types
-- ------------------------------------------------------------
INSERT INTO vehicles (vehicle_name, vehicle_type, fuel_type, max_payload_kg, year_manufactured, is_active) VALUES
('Boeing 747 Freighter',  'Plane',  'Jet-A',    112000.00, 2015, TRUE),
('Airbus A330F',          'Plane',  'Jet-A',     70000.00, 2018, TRUE),
('MAN TGX Heavy Truck',   'Truck',  'Diesel',    25000.00, 2019, TRUE),
('Volvo FH Truck',        'Truck',  'Diesel',    20000.00, 2020, TRUE),
('Ford Transit Van',      'Van',    'Diesel',     1500.00, 2021, TRUE),
('Renault Kangoo Van',    'Van',    'Electric',    800.00, 2022, TRUE),
('Maersk Container Ship', 'Ship',   'Heavy Fuel', 200000.00, 2010, TRUE),
('MSC Cargo Vessel',      'Ship',   'Heavy Fuel', 180000.00, 2012, TRUE);


-- ------------------------------------------------------------
-- INSERT: emission_rates
-- Real-world approximate CO2 emission factors
-- Source: IPCC / European Environment Agency
-- ------------------------------------------------------------
INSERT INTO emission_rates (vehicle_type, fuel_type, co2_per_km_per_kg, data_source, valid_from_date) VALUES
('Plane', 'Jet-A',      0.000602, 'IPCC',  '2020-01-01'),  -- planes: high emissions per km/kg
('Truck', 'Diesel',     0.000210, 'EEA',   '2020-01-01'),  -- trucks: moderate
('Van',   'Diesel',     0.000280, 'EEA',   '2020-01-01'),  -- vans: slightly higher per kg than trucks
('Van',   'Electric',   0.000050, 'IPCC',  '2021-01-01'),  -- electric vans: very low
('Ship',  'Heavy Fuel', 0.000016, 'IMO',   '2020-01-01');  -- ships: most efficient per km/kg


-- ------------------------------------------------------------
-- INSERT: fuel_prices
-- Snapshot of global fuel prices for reference
-- ------------------------------------------------------------
INSERT INTO fuel_prices (fuel_type, price_per_litre, currency, recorded_date, region) VALUES
('Diesel',     1.45, 'USD', '2024-01-01', 'Europe'),
('Diesel',     1.10, 'USD', '2024-01-01', 'South Asia'),
('Petrol',     1.55, 'USD', '2024-01-01', 'Europe'),
('Jet-A',      0.85, 'USD', '2024-01-01', 'Middle East'),
('Heavy Fuel', 0.55, 'USD', '2024-01-01', 'Global'),
('Electric',   0.18, 'USD', '2024-01-01', 'Europe');


-- ============================================================
-- PHASE 4: SIMULATE OPERATIONS — INSERT shipments, routes,
--          emission logs, goals, and alerts.
--          This is the "real usage" data of the system.
-- ============================================================


-- ------------------------------------------------------------
-- INSERT: shipments
-- 10 shipments from different customers going to different
-- destinations with various cargo types and priorities.
-- ------------------------------------------------------------
INSERT INTO shipments (customer_id, origin_location_id, dest_location_id, total_weight_kg, cargo_type, shipment_date, delivery_deadline, status, priority_level) VALUES
(1, 2,  3,   500.00, 'Electronics',  '2024-01-05', '2024-01-10', 'Delivered',  'Express'),    -- Lahore → Dubai
(2, 4,  6,   300.00, 'General',      '2024-01-08', '2024-01-15', 'Delivered',  'Standard'),   -- Frankfurt → Rotterdam
(3, 8,  5,  1500.00, 'Electronics',  '2024-01-10', '2024-01-20', 'Delivered',  'Critical'),   -- New York → Shanghai
(4, 6,  4,   200.00, 'Food',         '2024-01-12', '2024-01-14', 'Delivered',  'Express'),    -- Rotterdam → Frankfurt
(5, 5,  9,  8000.00, 'Manufacturing','2024-01-15', '2024-02-01', 'Delivered',  'Standard'),   -- Shanghai → Singapore
(1, 1,  10,  750.00, 'General',      '2024-01-18', '2024-01-25', 'Delivered',  'Standard'),   -- Karachi → Istanbul
(6, 7,  4,   120.00, 'Electronics',  '2024-01-20', '2024-01-22', 'Delivered',  'Express'),    -- London → Frankfurt
(3, 8,  4,  2000.00, 'Electronics',  '2024-01-22', '2024-02-05', 'In-Transit', 'Critical'),   -- New York → Frankfurt
(2, 4,  7,   450.00, 'General',      '2024-01-25', '2024-02-01', 'In-Transit', 'Standard'),   -- Frankfurt → London
(5, 5,  1,  5000.00, 'Manufacturing','2024-01-28', '2024-02-10', 'Pending',    'Standard');   -- Shanghai → Karachi


-- ------------------------------------------------------------
-- INSERT: routes (multi-leg journeys)
-- Shipments are broken into legs. Each leg = different vehicle.
-- Leg numbers show the sequence of travel.
-- ------------------------------------------------------------
INSERT INTO routes (shipment_id, leg_number, origin_location_id, dest_location_id, vehicle_id, distance_km, actual_weight_kg, departure_time, arrival_time) VALUES
-- Shipment 1: Lahore → Dubai (Truck then Plane)
(1, 1, 2, 1,  3, 1240.00, 500.00, '2024-01-05 08:00:00', '2024-01-06 20:00:00'),  -- Truck: Lahore→Karachi
(1, 2, 1, 3,  1,  990.00, 500.00, '2024-01-07 06:00:00', '2024-01-07 09:00:00'),  -- Plane: Karachi→Dubai

-- Shipment 2: Frankfurt → Rotterdam (Van)
(2, 1, 4, 6,  5,  520.00, 300.00, '2024-01-08 09:00:00', '2024-01-08 14:00:00'),  -- Van: Frankfurt→Rotterdam

-- Shipment 3: New York → Shanghai (Plane)
(3, 1, 8, 5,  1, 11800.00, 1500.00, '2024-01-10 22:00:00', '2024-01-12 18:00:00'), -- Plane: NY→Shanghai

-- Shipment 4: Rotterdam → Frankfurt (Van)
(4, 1, 6, 4,  5,  520.00, 200.00, '2024-01-12 07:00:00', '2024-01-12 12:00:00'),  -- Van: Rotterdam→Frankfurt

-- Shipment 5: Shanghai → Singapore (Ship then Truck)
(5, 1, 5, 9,  7, 3300.00, 8000.00, '2024-01-15 10:00:00', '2024-01-19 08:00:00'), -- Ship: Shanghai→Singapore
(5, 2, 9, 9,  3,   25.00, 8000.00, '2024-01-19 10:00:00', '2024-01-19 11:00:00'), -- Truck: Port→Warehouse

-- Shipment 6: Karachi → Istanbul (Plane)
(6, 1, 1, 10, 2, 3870.00, 750.00, '2024-01-18 14:00:00', '2024-01-18 20:00:00'),  -- Plane: Karachi→Istanbul

-- Shipment 7: London → Frankfurt (Van electric)
(7, 1, 7, 4,  6,  750.00, 120.00, '2024-01-20 08:00:00', '2024-01-20 16:00:00'),  -- Electric Van

-- Shipment 8: New York → Frankfurt (Plane)
(8, 1, 8, 4,  1, 6200.00, 2000.00, '2024-01-22 00:00:00', '2024-01-22 14:00:00'), -- Plane: NY→Frankfurt

-- Shipment 9: Frankfurt → London (Truck)
(9, 1, 4, 7,  3,  750.00, 450.00, '2024-01-25 09:00:00', '2024-01-25 18:00:00'), -- Truck: Frankfurt→London

-- Shipment 10: Shanghai → Karachi (Ship)
(10, 1, 5, 1, 7, 5600.00, 5000.00, '2024-01-28 06:00:00', '2024-02-02 06:00:00'); -- Ship: Shanghai→Karachi


-- ------------------------------------------------------------
-- INSERT: emission_logs
-- Manually calculate and insert CO2 for each route leg.
-- Formula: co2 = distance_km x weight_kg x co2_per_km_per_kg
--
-- Rate IDs reference emission_rates table:
--   1 = Plane/Jet-A       (0.000602)
--   2 = Plane/Jet-A       (same rate, using rate_id=1)
--   3 = Truck/Diesel      (0.000210)
--   4 = Van/Diesel        (0.000280)
--   5 = Van/Electric      (0.000050)
--   6 = Ship/Heavy Fuel   (0.000016)
-- ------------------------------------------------------------
INSERT INTO emission_logs (route_id, shipment_id, vehicle_id, rate_id, distance_km, weight_kg, co2_emitted_kg) VALUES
-- Route 1: Truck Lahore→Karachi: 1240 x 500 x 0.000210 = 130.2
(1,  1, 3, 3, 1240.00,  500.00,  130.2000),
-- Route 2: Plane Karachi→Dubai: 990 x 500 x 0.000602 = 297.99
(2,  1, 1, 1,  990.00,  500.00,  297.9900),
-- Route 3: Van Frankfurt→Rotterdam: 520 x 300 x 0.000280 = 43.68
(3,  2, 5, 4,  520.00,  300.00,   43.6800),
-- Route 4: Plane NY→Shanghai: 11800 x 1500 x 0.000602 = 10655.4
(4,  3, 1, 1, 11800.00, 1500.00, 10655.4000),
-- Route 5: Van Rotterdam→Frankfurt: 520 x 200 x 0.000280 = 29.12
(5,  4, 5, 4,  520.00,  200.00,   29.1200),
-- Route 6: Ship Shanghai→Singapore: 3300 x 8000 x 0.000016 = 422.4
(6,  5, 7, 5, 3300.00, 8000.00,  422.4000),
-- Route 7: Truck Singapore warehouse: 25 x 8000 x 0.000210 = 42.0
(7,  5, 3, 3,   25.00, 8000.00,   42.0000),
-- Route 8: Plane Karachi→Istanbul: 3870 x 750 x 0.000602 = 1748.715
(8,  6, 2, 1, 3870.00,  750.00, 1748.7150),
-- Route 9: Electric Van London→Frankfurt: 750 x 120 x 0.000050 = 4.5
(9,  7, 6, 5,  750.00,  120.00,    4.5000),
-- Route 10: Plane NY→Frankfurt: 6200 x 2000 x 0.000602 = 7464.8
(10, 8, 1, 1, 6200.00, 2000.00, 7464.8000),
-- Route 11: Truck Frankfurt→London: 750 x 450 x 0.000210 = 70.875
(11, 9, 3, 3,  750.00,  450.00,   70.8750),
-- Route 12: Ship Shanghai→Karachi: 5600 x 5000 x 0.000016 = 448.0
(12, 10, 7, 5, 5600.00, 5000.00, 448.0000);


-- ------------------------------------------------------------
-- INSERT: carbon_goals
-- Each customer sets a CO2 budget for January 2024
-- ------------------------------------------------------------
INSERT INTO carbon_goals (customer_id, target_month, target_year, max_co2_kg, goal_type) VALUES
(1, 1, 2024, 800.00,  'Monthly-Total'),
(2, 1, 2024, 600.00,  'Monthly-Total'),
(3, 1, 2024, 1200.00, 'Monthly-Total'),
(4, 1, 2024, 500.00,  'Monthly-Total'),
(5, 1, 2024, 2000.00, 'Monthly-Total'),
(6, 1, 2024, 400.00,  'Monthly-Total');


-- ------------------------------------------------------------
-- INSERT: goal_performance
-- End-of-month results: did customers meet their goals?
-- Values based on our emission_logs inserts above.
-- ------------------------------------------------------------
INSERT INTO goal_performance (goal_id, actual_co2_kg, variance_kg, met_goal) VALUES
(1, 2176.7050,  1376.7050, 'NO'),   -- Customer 1 (SwiftCargo): way over budget
(2,   43.6800,  -556.3200, 'YES'),  -- Customer 2 (GreenGoods): under budget
(3, 18120.2000, 16920.2000, 'NO'),  -- Customer 3 (TechShip): massively over
(4,   29.1200,  -470.8800, 'YES'),  -- Customer 4 (FreshRoute): well under
(5,  464.4000, -1535.6000, 'YES'),  -- Customer 5 (HeavyHaul): under budget
(6,    4.5000,  -395.5000, 'YES');  -- Customer 6 (EcoDrop): very green


-- ------------------------------------------------------------
-- INSERT: alerts
-- Triggered for customers who exceeded their carbon goals
-- ------------------------------------------------------------
INSERT INTO alerts (shipment_id, customer_id, alert_type, message) VALUES
(1, 1, 'OverBudget',    'SwiftCargo exceeded their January CO2 budget. Actual: 2176.7 kg vs limit 800 kg.'),
(3, 3, 'HighEmission',  'TechShip Shipment #3 (NY→Shanghai) produced 10,655 kg CO2 — single highest emitting shipment.'),
(8, 3, 'OverBudget',    'TechShip exceeded their January CO2 budget. Consider sea freight for future NY→EU routes.');


-- ============================================================
-- PHASE 5: ALTER TABLE — Evolve the schema mid-project
--          This simulates real-world schema changes as
--          new requirements come in after the initial build.
-- ============================================================


-- ------------------------------------------------------------
-- ALTER 1: Add a 'notes' column to shipments
-- Business asked to add optional notes per shipment
-- ------------------------------------------------------------
ALTER TABLE shipments
ADD COLUMN notes TEXT DEFAULT NULL;


-- ------------------------------------------------------------
-- ALTER 2: Add a 'carbon_offset_kg' column to emission_logs
-- New requirement: track any carbon credits purchased to offset emissions
-- ------------------------------------------------------------
ALTER TABLE emission_logs
ADD COLUMN carbon_offset_kg DECIMAL(10,4) DEFAULT 0.0000;


-- ------------------------------------------------------------
-- ALTER 3: Add an index on shipment_date for faster reporting queries
-- ------------------------------------------------------------
ALTER TABLE shipments
ADD INDEX idx_shipment_date (shipment_date);


-- ------------------------------------------------------------
-- ALTER 4: Increase the length of cargo_type column
-- Some new cargo categories are longer strings
-- ------------------------------------------------------------
ALTER TABLE shipments
MODIFY COLUMN cargo_type VARCHAR(100) NOT NULL;


-- ============================================================
-- PHASE 6: UPDATE records
--          Simulate real-world corrections and status changes
-- ============================================================


-- ------------------------------------------------------------
-- UPDATE 1: Mark shipment 8 and 9 as Delivered
-- They were In-Transit — now they have arrived
-- ------------------------------------------------------------
UPDATE shipments
SET status = 'Delivered'
WHERE shipment_id IN (8, 9);


-- ------------------------------------------------------------
-- UPDATE 2: Add notes to a high-emission shipment
-- Business wants to flag it for review
-- ------------------------------------------------------------
UPDATE shipments
SET notes = 'High emission shipment — flagged for route optimization review.'
WHERE shipment_id = 3;


-- ------------------------------------------------------------
-- UPDATE 3: Apply a carbon offset to the electric van route
-- EcoDrop purchased carbon credits for their shipment
-- ------------------------------------------------------------
UPDATE emission_logs
SET carbon_offset_kg = 4.5000
WHERE route_id = 9;


-- ------------------------------------------------------------
-- UPDATE 4: Correct an emission log where weight was entered wrong
-- Route 7 actual weight should have been 7500 kg, not 8000
-- Recalculate: 25 x 7500 x 0.000210 = 39.375
-- ------------------------------------------------------------
UPDATE emission_logs
SET weight_kg = 7500.00,
    co2_emitted_kg = 39.3750
WHERE route_id = 7;


-- ------------------------------------------------------------
-- UPDATE 5: Deactivate an old vehicle that's being retired
-- ------------------------------------------------------------
UPDATE vehicles
SET is_active = FALSE
WHERE vehicle_id = 8;  -- MSC Cargo Vessel retired


-- ============================================================
-- PHASE 7: VIEWS — Pre-built reusable report queries
--          Views let us query complex joins as if they
--          were simple single tables.
-- ============================================================


-- ------------------------------------------------------------
-- VIEW 1: v_shipment_co2_summary
-- Shows total CO2 emitted per shipment (sum of all its legs)
-- Joins: shipments → customers, emission_logs
-- ------------------------------------------------------------
CREATE VIEW v_shipment_co2_summary AS
SELECT
    s.shipment_id,
    c.company_name,
    s.cargo_type,
    s.total_weight_kg,
    s.shipment_date,
    s.status,
    ROUND(SUM(el.co2_emitted_kg), 4)  AS total_co2_kg,
    COUNT(el.log_id)                   AS total_legs
FROM shipments s
JOIN customers c     ON s.customer_id = c.customer_id
JOIN emission_logs el ON s.shipment_id = el.shipment_id
GROUP BY
    s.shipment_id, c.company_name, s.cargo_type,
    s.total_weight_kg, s.shipment_date, s.status;


-- ------------------------------------------------------------
-- VIEW 2: v_customer_monthly_report
-- Total CO2 per customer per month — the main dashboard view
-- ------------------------------------------------------------
CREATE VIEW v_customer_monthly_report AS
SELECT
    c.customer_id,
    c.company_name,
    c.country,
    MONTH(el.calculated_at)  AS report_month,
    YEAR(el.calculated_at)   AS report_year,
    ROUND(SUM(el.co2_emitted_kg), 4) AS total_co2_kg,
    COUNT(DISTINCT el.shipment_id)   AS total_shipments,
    c.carbon_budget_kg               AS monthly_budget_kg,
    ROUND(SUM(el.co2_emitted_kg) - c.carbon_budget_kg, 4) AS budget_variance_kg
FROM customers c
JOIN emission_logs el ON c.customer_id = (
    SELECT s.customer_id FROM shipments s WHERE s.shipment_id = el.shipment_id
)
GROUP BY
    c.customer_id, c.company_name, c.country,
    MONTH(el.calculated_at), YEAR(el.calculated_at),
    c.carbon_budget_kg;


-- ------------------------------------------------------------
-- VIEW 3: v_fleet_efficiency
-- Ranks all vehicles by their CO2 efficiency
-- (lower co2_per_km_per_kg = greener vehicle type)
-- ------------------------------------------------------------
CREATE VIEW v_fleet_efficiency AS
SELECT
    v.vehicle_id,
    v.vehicle_name,
    v.vehicle_type,
    v.fuel_type,
    er.co2_per_km_per_kg,
    ROUND(SUM(el.co2_emitted_kg), 4) AS total_co2_produced,
    COUNT(el.log_id)                  AS times_used,
    RANK() OVER (ORDER BY er.co2_per_km_per_kg ASC) AS efficiency_rank
FROM vehicles v
JOIN emission_rates er ON v.vehicle_type = er.vehicle_type AND v.fuel_type = er.fuel_type
LEFT JOIN emission_logs el ON v.vehicle_id = el.vehicle_id
GROUP BY
    v.vehicle_id, v.vehicle_name, v.vehicle_type,
    v.fuel_type, er.co2_per_km_per_kg;


-- ------------------------------------------------------------
-- VIEW 4: v_route_detail
-- Full detail for every route leg including CO2 and locations
-- ------------------------------------------------------------
CREATE VIEW v_route_detail AS
SELECT
    r.route_id,
    r.shipment_id,
    r.leg_number,
    ol.city                   AS from_city,
    dl.city                   AS to_city,
    v.vehicle_name,
    v.vehicle_type,
    r.distance_km,
    r.actual_weight_kg,
    el.co2_emitted_kg,
    el.carbon_offset_kg,
    ROUND(el.co2_emitted_kg - el.carbon_offset_kg, 4) AS net_co2_kg
FROM routes r
JOIN locations ol       ON r.origin_location_id = ol.location_id
JOIN locations dl       ON r.dest_location_id   = dl.location_id
JOIN vehicles v         ON r.vehicle_id         = v.vehicle_id
LEFT JOIN emission_logs el ON r.route_id        = el.route_id;


-- ------------------------------------------------------------
-- VIEW 5: v_goal_status
-- Red/Yellow/Green status for each customer's carbon goal
-- ------------------------------------------------------------
CREATE VIEW v_goal_status AS
SELECT
    c.company_name,
    cg.target_month,
    cg.target_year,
    cg.max_co2_kg             AS goal_kg,
    gp.actual_co2_kg,
    gp.variance_kg,
    gp.met_goal,
    CASE
        WHEN gp.variance_kg <= 0                          THEN 'GREEN'  -- under budget
        WHEN gp.variance_kg > 0 AND gp.variance_kg < 200 THEN 'YELLOW' -- slightly over
        ELSE                                                   'RED'    -- way over
    END AS status_color
FROM carbon_goals cg
JOIN customers c         ON cg.customer_id    = c.customer_id
LEFT JOIN goal_performance gp ON cg.goal_id  = gp.goal_id;


-- ============================================================
-- PHASE 8: STORED PROCEDURES
--          Reusable SQL routines for common operations.
--          Call them with CALL procedure_name(args).
-- ============================================================


-- Change delimiter so MySQL doesn't break on semicolons inside procedures
DELIMITER $$


-- ------------------------------------------------------------
-- PROCEDURE 1: calculate_and_log_emission
-- Given a route_id, this procedure automatically:
-- 1. Fetches the route details (distance, weight, vehicle)
-- 2. Looks up the correct emission rate
-- 3. Calculates CO2 using the formula
-- 4. Inserts the result into emission_logs
-- Usage: CALL calculate_and_log_emission(13);
-- ------------------------------------------------------------
CREATE PROCEDURE calculate_and_log_emission(IN p_route_id INT)
BEGIN
    -- Local variables to hold fetched values
    DECLARE v_shipment_id  INT;
    DECLARE v_vehicle_id   INT;
    DECLARE v_distance     DECIMAL(10,2);
    DECLARE v_weight       DECIMAL(10,2);
    DECLARE v_vehicle_type VARCHAR(40);
    DECLARE v_fuel_type    VARCHAR(40);
    DECLARE v_rate_id      INT;
    DECLARE v_rate         DECIMAL(10,6);
    DECLARE v_co2          DECIMAL(10,4);

    -- Step 1: Get route details
    SELECT r.shipment_id, r.vehicle_id, r.distance_km, r.actual_weight_kg,
           v.vehicle_type, v.fuel_type
    INTO v_shipment_id, v_vehicle_id, v_distance, v_weight, v_vehicle_type, v_fuel_type
    FROM routes r
    JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    WHERE r.route_id = p_route_id;

    -- Step 2: Get the matching emission rate
    SELECT rate_id, co2_per_km_per_kg
    INTO v_rate_id, v_rate
    FROM emission_rates
    WHERE vehicle_type = v_vehicle_type
      AND fuel_type    = v_fuel_type
    LIMIT 1;

    -- Step 3: Apply the formula
    SET v_co2 = v_distance * v_weight * v_rate;

    -- Step 4: Insert result into emission_logs
    INSERT INTO emission_logs (route_id, shipment_id, vehicle_id, rate_id, distance_km, weight_kg, co2_emitted_kg)
    VALUES (p_route_id, v_shipment_id, v_vehicle_id, v_rate_id, v_distance, v_weight, v_co2);

    -- Confirm with a message
    SELECT CONCAT('CO2 logged for route ', p_route_id, ': ', v_co2, ' kg') AS result;
END$$


-- ------------------------------------------------------------
-- PROCEDURE 2: get_customer_report
-- Prints a full emission summary for a given customer
-- Usage: CALL get_customer_report(1);
-- ------------------------------------------------------------
CREATE PROCEDURE get_customer_report(IN p_customer_id INT)
BEGIN
    SELECT
        c.company_name,
        s.shipment_id,
        s.shipment_date,
        s.cargo_type,
        s.status,
        ROUND(SUM(el.co2_emitted_kg), 4) AS total_co2_kg
    FROM customers c
    JOIN shipments s     ON c.customer_id  = s.customer_id
    JOIN emission_logs el ON s.shipment_id = el.shipment_id
    WHERE c.customer_id = p_customer_id
    GROUP BY c.company_name, s.shipment_id, s.shipment_date, s.cargo_type, s.status
    ORDER BY s.shipment_date;
END$$


-- ------------------------------------------------------------
-- PROCEDURE 3: add_new_shipment
-- Inserts a new shipment record cleanly with one call.
-- Usage: CALL add_new_shipment(1, 2, 3, 600, 'Electronics', '2024-02-01', '2024-02-10', 'Standard');
-- ------------------------------------------------------------
CREATE PROCEDURE add_new_shipment(
    IN p_customer_id   INT,
    IN p_origin        INT,
    IN p_destination   INT,
    IN p_weight        DECIMAL(10,2),
    IN p_cargo_type    VARCHAR(100),
    IN p_ship_date     DATE,
    IN p_deadline      DATE,
    IN p_priority      VARCHAR(20)
)
BEGIN
    INSERT INTO shipments
        (customer_id, origin_location_id, dest_location_id, total_weight_kg,
         cargo_type, shipment_date, delivery_deadline, status, priority_level)
    VALUES
        (p_customer_id, p_origin, p_destination, p_weight,
         p_cargo_type, p_ship_date, p_deadline, 'Pending', p_priority);

    SELECT LAST_INSERT_ID() AS new_shipment_id;
END$$


-- Reset delimiter back to normal
DELIMITER ;


-- ============================================================
-- PHASE 9: TRIGGERS
--          Auto-fire actions when data changes.
--          No manual intervention needed.
-- ============================================================


DELIMITER $$


-- ------------------------------------------------------------
-- TRIGGER 1: after_emission_log_insert
-- After any new emission log is inserted, this trigger checks
-- if the customer's monthly total now exceeds their budget.
-- If yes, it automatically inserts an Alert.
-- ------------------------------------------------------------
CREATE TRIGGER after_emission_log_insert
AFTER INSERT ON emission_logs
FOR EACH ROW
BEGIN
    DECLARE v_customer_id   INT;
    DECLARE v_monthly_total DECIMAL(10,4);
    DECLARE v_budget        DECIMAL(10,2);

    -- Find which customer owns this shipment
    SELECT s.customer_id INTO v_customer_id
    FROM shipments s
    WHERE s.shipment_id = NEW.shipment_id;

    -- Sum up their total CO2 for the current month
    SELECT ROUND(SUM(el.co2_emitted_kg), 4) INTO v_monthly_total
    FROM emission_logs el
    JOIN shipments s ON el.shipment_id = s.shipment_id
    WHERE s.customer_id = v_customer_id
      AND MONTH(el.calculated_at) = MONTH(NEW.calculated_at)
      AND YEAR(el.calculated_at)  = YEAR(NEW.calculated_at);

    -- Get the customer's monthly carbon budget
    SELECT carbon_budget_kg INTO v_budget
    FROM customers
    WHERE customer_id = v_customer_id;

    -- If they exceeded the budget, fire an alert
    IF v_monthly_total > v_budget THEN
        INSERT INTO alerts (shipment_id, customer_id, alert_type, message)
        VALUES (
            NEW.shipment_id,
            v_customer_id,
            'OverBudget',
            CONCAT('Customer ', v_customer_id, ' has exceeded monthly CO2 budget. Total: ',
                   v_monthly_total, ' kg vs budget: ', v_budget, ' kg.')
        );
    END IF;
END$$


-- ------------------------------------------------------------
-- TRIGGER 2: before_route_insert
-- Validates that a route's weight doesn't exceed
-- the vehicle's maximum payload before inserting.
-- Raises an error if it does.
-- ------------------------------------------------------------
CREATE TRIGGER before_route_insert
BEFORE INSERT ON routes
FOR EACH ROW
BEGIN
    DECLARE v_max_payload DECIMAL(10,2);

    SELECT max_payload_kg INTO v_max_payload
    FROM vehicles
    WHERE vehicle_id = NEW.vehicle_id;

    IF NEW.actual_weight_kg > v_max_payload THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: Cargo weight exceeds vehicle maximum payload capacity.';
    END IF;
END$$


DELIMITER ;


-- ============================================================
-- PHASE 10: INDEXES — Speed up common queries
--           Indexes tell MySQL to pre-sort columns
--           so lookups are faster on large datasets.
-- ============================================================


-- Index on emission_logs.shipment_id (very frequently joined)
CREATE INDEX idx_emlog_shipment    ON emission_logs(shipment_id);

-- Index on emission_logs.vehicle_id
CREATE INDEX idx_emlog_vehicle     ON emission_logs(vehicle_id);

-- Index on routes.shipment_id (join used in almost every query)
CREATE INDEX idx_route_shipment    ON routes(shipment_id);

-- Index on shipments.customer_id
CREATE INDEX idx_shipment_customer ON shipments(customer_id);

-- Index on alerts.customer_id
CREATE INDEX idx_alert_customer    ON alerts(customer_id);


-- ============================================================
-- PHASE 11: ANALYTICAL QUERIES
--           These are the "business intelligence" queries.
--           They answer real questions using JOINs,
--           aggregates, subqueries, CTEs, and window functions.
-- ============================================================


-- ------------------------------------------------------------
-- QUERY 1: Total CO2 per shipment (INNER JOIN across 5 tables)
-- Answer: How much CO2 did each shipment produce end-to-end?
-- ------------------------------------------------------------
SELECT
    s.shipment_id,
    c.company_name,
    orig.city                            AS from_city,
    dest.city                            AS to_city,
    s.cargo_type,
    s.total_weight_kg,
    s.priority_level,
    ROUND(SUM(el.co2_emitted_kg), 4)    AS total_co2_kg
FROM shipments s
JOIN customers c         ON s.customer_id         = c.customer_id
JOIN locations orig      ON s.origin_location_id  = orig.location_id
JOIN locations dest      ON s.dest_location_id    = dest.location_id
JOIN emission_logs el    ON s.shipment_id         = el.shipment_id
GROUP BY
    s.shipment_id, c.company_name, orig.city, dest.city,
    s.cargo_type, s.total_weight_kg, s.priority_level
ORDER BY total_co2_kg DESC;


-- ------------------------------------------------------------
-- QUERY 2: Monthly CO2 per customer vs their budget
-- Answer: Who is over budget and by how much?
-- ------------------------------------------------------------
SELECT
    c.company_name,
    ROUND(SUM(el.co2_emitted_kg), 4)              AS actual_co2_kg,
    c.carbon_budget_kg                             AS budget_kg,
    ROUND(SUM(el.co2_emitted_kg) - c.carbon_budget_kg, 4) AS variance_kg,
    IF(SUM(el.co2_emitted_kg) <= c.carbon_budget_kg, 'MET', 'EXCEEDED') AS goal_status
FROM customers c
JOIN shipments s     ON c.customer_id  = s.customer_id
JOIN emission_logs el ON s.shipment_id = el.shipment_id
WHERE MONTH(el.calculated_at) = 1 AND YEAR(el.calculated_at) = 2024
GROUP BY c.customer_id, c.company_name, c.carbon_budget_kg
ORDER BY variance_kg DESC;


-- ------------------------------------------------------------
-- QUERY 3: Vehicle type CO2 efficiency ranking (WINDOW FUNCTION)
-- Answer: Which vehicle type is the greenest?
-- ------------------------------------------------------------
SELECT
    v.vehicle_type,
    v.fuel_type,
    ROUND(AVG(er.co2_per_km_per_kg), 6)     AS avg_emission_rate,
    ROUND(SUM(el.co2_emitted_kg), 4)         AS total_co2_produced,
    COUNT(el.log_id)                          AS times_used,
    RANK() OVER (ORDER BY AVG(er.co2_per_km_per_kg) ASC) AS green_rank
FROM vehicles v
JOIN emission_rates er  ON v.vehicle_type = er.vehicle_type AND v.fuel_type = er.fuel_type
JOIN emission_logs el   ON v.vehicle_id   = el.vehicle_id
GROUP BY v.vehicle_type, v.fuel_type
ORDER BY green_rank;


-- ------------------------------------------------------------
-- QUERY 4: Most polluting route corridors (GROUP BY + ORDER BY)
-- Answer: Which city-to-city route produces the most CO2?
-- ------------------------------------------------------------
SELECT
    ol.city            AS from_city,
    dl.city            AS to_city,
    COUNT(r.route_id)  AS times_used,
    ROUND(SUM(el.co2_emitted_kg), 4) AS total_co2_kg,
    ROUND(AVG(el.co2_emitted_kg), 4) AS avg_co2_per_trip
FROM routes r
JOIN locations ol      ON r.origin_location_id = ol.location_id
JOIN locations dl      ON r.dest_location_id   = dl.location_id
JOIN emission_logs el  ON r.route_id           = el.route_id
GROUP BY ol.city, dl.city
ORDER BY total_co2_kg DESC;


-- ------------------------------------------------------------
-- QUERY 5: Running cumulative CO2 over time (WINDOW FUNCTION)
-- Answer: How is our total fleet CO2 accumulating day by day?
-- ------------------------------------------------------------
SELECT
    DATE(el.calculated_at)        AS log_date,
    ROUND(SUM(el.co2_emitted_kg), 4) AS daily_co2_kg,
    ROUND(SUM(SUM(el.co2_emitted_kg))
          OVER (ORDER BY DATE(el.calculated_at)), 4) AS running_total_co2_kg
FROM emission_logs el
GROUP BY DATE(el.calculated_at)
ORDER BY log_date;


-- ------------------------------------------------------------
-- QUERY 6: Shipments heavier than average (SUBQUERY)
-- Answer: Which shipments carry above-average weight?
-- ------------------------------------------------------------
SELECT
    s.shipment_id,
    c.company_name,
    s.total_weight_kg,
    ROUND((SELECT AVG(total_weight_kg) FROM shipments), 2) AS fleet_avg_weight_kg
FROM shipments s
JOIN customers c ON s.customer_id = c.customer_id
WHERE s.total_weight_kg > (SELECT AVG(total_weight_kg) FROM shipments)
ORDER BY s.total_weight_kg DESC;


-- ------------------------------------------------------------
-- QUERY 7: Customers who exceeded budget (SUBQUERY with IN)
-- Answer: Which customers are over their carbon limit?
-- ------------------------------------------------------------
SELECT
    c.company_name,
    c.country,
    c.carbon_budget_kg
FROM customers c
WHERE c.customer_id IN (
    SELECT s.customer_id
    FROM shipments s
    JOIN emission_logs el ON s.shipment_id = el.shipment_id
    GROUP BY s.customer_id
    HAVING SUM(el.co2_emitted_kg) > MAX(c.carbon_budget_kg)
);


-- ------------------------------------------------------------
-- QUERY 8: Full supply chain breakdown using CTE
-- Answer: Step-by-step CO2 for each leg of every multi-leg shipment
-- ------------------------------------------------------------
WITH leg_details AS (
    -- Step 1: Get all route legs with CO2
    SELECT
        r.shipment_id,
        r.leg_number,
        ol.city             AS from_city,
        dl.city             AS to_city,
        v.vehicle_type,
        r.distance_km,
        r.actual_weight_kg,
        el.co2_emitted_kg
    FROM routes r
    JOIN locations ol     ON r.origin_location_id = ol.location_id
    JOIN locations dl     ON r.dest_location_id   = dl.location_id
    JOIN vehicles v       ON r.vehicle_id         = v.vehicle_id
    JOIN emission_logs el ON r.route_id           = el.route_id
),
shipment_totals AS (
    -- Step 2: Sum up all legs per shipment
    SELECT
        shipment_id,
        ROUND(SUM(co2_emitted_kg), 4) AS total_co2_kg,
        COUNT(*)                       AS number_of_legs
    FROM leg_details
    GROUP BY shipment_id
)
-- Step 3: Join both CTEs and show the full picture
SELECT
    ld.shipment_id,
    ld.leg_number,
    ld.from_city,
    ld.to_city,
    ld.vehicle_type,
    ld.distance_km,
    ld.co2_emitted_kg         AS leg_co2_kg,
    st.total_co2_kg           AS shipment_total_co2_kg,
    st.number_of_legs
FROM leg_details ld
JOIN shipment_totals st ON ld.shipment_id = st.shipment_id
ORDER BY ld.shipment_id, ld.leg_number;


-- ------------------------------------------------------------
-- QUERY 9: Each customer's CO2 vs company average (WINDOW FUNCTION with PARTITION BY)
-- Answer: How does each shipment compare to that customer's own average?
-- ------------------------------------------------------------
SELECT
    c.company_name,
    s.shipment_id,
    s.shipment_date,
    ROUND(SUM(el.co2_emitted_kg), 4)  AS shipment_co2_kg,
    ROUND(AVG(SUM(el.co2_emitted_kg))
          OVER (PARTITION BY c.customer_id), 4) AS customer_avg_co2_kg
FROM customers c
JOIN shipments s     ON c.customer_id  = s.customer_id
JOIN emission_logs el ON s.shipment_id = el.shipment_id
GROUP BY c.customer_id, c.company_name, s.shipment_id, s.shipment_date
ORDER BY c.company_name, s.shipment_date;


-- ------------------------------------------------------------
-- QUERY 10: Use the views we created to get quick reports
-- ------------------------------------------------------------

-- View 1: Shipment CO2 summary
SELECT * FROM v_shipment_co2_summary ORDER BY total_co2_kg DESC;

-- View 2: Goal status with color coding
SELECT * FROM v_goal_status ORDER BY status_color;

-- View 3: Full route detail with net CO2 (after offsets)
SELECT * FROM v_route_detail ORDER BY shipment_id, leg_number;

-- View 4: Fleet efficiency ranking
SELECT * FROM v_fleet_efficiency ORDER BY efficiency_rank;


-- ============================================================
-- PHASE 12: DEMONSTRATE STORED PROCEDURES AND TRIGGERS
-- ============================================================


-- ------------------------------------------------------------
-- Call the customer report procedure for SwiftCargo (ID = 1)
-- ------------------------------------------------------------
CALL get_customer_report(1);


-- ------------------------------------------------------------
-- Add a brand new shipment using our procedure
-- Customer 2, from Frankfurt (4) to London (7), 350 kg, Express
-- ------------------------------------------------------------
CALL add_new_shipment(2, 4, 7, 350.00, 'General Goods', '2024-02-05', '2024-02-10', 'Express');


-- ============================================================
-- END OF PROJECT
-- ============================================================
-- Summary of what was built:
--   11 Tables     : customers, locations, vehicles, emission_rates,
--                   shipments, routes, emission_logs, carbon_goals,
--                   goal_performance, alerts, fuel_prices
--    5 Views      : shipment summary, monthly report, fleet efficiency,
--                   route detail, goal status
--    3 Procedures : calculate emission, customer report, add shipment
--    2 Triggers   : auto-alert on budget breach, payload validation
--    5 Indexes    : for performance on most-joined columns
--   12 Queries    : JOINs, aggregates, subqueries, CTEs, window functions
-- ============================================================