
-- Insert sample users
INSERT INTO users (username, email, password_hash, first_name, last_name, phone, date_of_birth, gender, role_id, email_verified, is_active) VALUES
('admin', 'admin@gamingstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', '+1234567890', '1985-05-15', 'other', 1, TRUE, TRUE),
('john_doe', 'john.doe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', '+1987654321', '1990-08-22', 'male', 3, TRUE, TRUE),
('jane_smith', 'jane.smith@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', '+1122334455', '1992-03-10', 'female', 3, TRUE, TRUE),
('peter_jones', 'peter.jones@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Peter', 'Jones', '+1555666777', '1988-11-30', 'male', 3, FALSE, TRUE),
('sara_williams', 'sara.williams@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sara', 'Williams', '+1444888999', '1995-07-18', 'female', 3, TRUE, TRUE),
('manager_mark', 'mark.manager@gamingstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mark', 'Manager', '+1777222333', '1980-01-25', 'male', 2, TRUE, TRUE);

-- Get user IDs for later use (assuming they are 1 to 6)
SET @admin_id = 1;
SET @john_id = 2;
SET @jane_id = 3;
SET @peter_id = 4;
SET @sara_id = 5;
SET @manager_id = 6;

-- Insert user addresses
INSERT INTO user_addresses (user_id, title, first_name, last_name, country, state, city, address, postal_code, phone, is_default) VALUES
(@john_id, 'Home', 'John', 'Doe', 'United States', 'California', 'Los Angeles', '123 Main St', '90210', '+1987654321', TRUE),
(@jane_id, 'Home', 'Jane', 'Smith', 'United Kingdom', 'England', 'London', '456 Oxford St', 'W1C 1AP', '+1122334455', TRUE),
(@peter_id, 'Home', 'Peter', 'Jones', 'Canada', 'Ontario', 'Toronto', '789 Queen St W', 'M5V 3A8', '+1555666777', TRUE),
(@sara_id, 'Home', 'Sara', 'Williams', 'Australia', 'New South Wales', 'Sydney', '321 George St', '2000', '+1444888999', TRUE);

-- ===== Publishers & Developers =====
INSERT INTO publishers (name, slug, description, website) VALUES
('CD Projekt Red', 'cd-projekt-red', 'Polish video game developer and publisher.', 'https://www.cdprojektred.com'),
('FromSoftware, Inc.', 'fromsoftware', 'Japanese video game development company.', 'https://www.fromsoftware.jp'),
('Electronic Arts', 'ea', 'American video game company.', 'https://www.ea.com'),
('Nintendo', 'nintendo', 'Japanese multinational video game company.', 'https://www.nintendo.com'),
('Valve Corporation', 'valve', 'American video game developer and publisher.', 'https://www.valvesoftware.com');

INSERT INTO developers (name, slug, description, website) VALUES
('CD Projekt Red', 'cd-projekt-red-dev', 'The development studio behind The Witcher series.', 'https://www.cdprojektred.com'),
('FromSoftware, Inc.', 'fromsoftware-dev', 'Known for Dark Souls, Bloodborne, and Elden Ring.', 'https://www.fromsoftware.jp'),
('EA Sports', 'ea-sports', 'A division of EA known for sports games.', 'https://www.easports.com'),
('Nintendo EPD', 'nintendo-epd', 'Nintendo\'s largest internal division.', 'https://www.nintendo.com'),
('Valve Corporation', 'valve-dev', 'Creator of Half-Life, Portal, and Dota 2.', 'https://www.valvesoftware.com');

-- Get IDs for later use
SET @cdpr_pub_id = 1;
SET @fromsoftware_pub_id = 2;
SET @ea_pub_id = 3;
SET @nintendo_pub_id = 4;
SET @valve_pub_id = 5;

SET @cdpr_dev_id = 1;
SET @fromsoftware_dev_id = 2;
SET @ea_dev_id = 3;
SET @nintendo_dev_id = 4;
SET @valve_dev_id = 5;

-- ===== Products & Related Tables =====
-- Insert sample products
INSERT INTO products (title, slug, short_description, description, sku, price, sale_price, discount_percentage, key_count, stock_status, featured_image, gallery, release_date, age_rating, publisher_id, developer_id, is_featured, is_trending, status, average_rating, reviews_count) VALUES
('Cyberpunk 2077', 'cyberpunk-2077', 'An open-world, action-adventure story set in Night City.', 'Cyberpunk 2077 is an open-world, action-adventure story set in Night City, a megalopolis obsessed with power, glamour and body modification. You play as V, a mercenary outlaw going after a one-of-a-kind implant that is the key to immortality.', 'CP2077-001', 59.99, 29.99, 50.00, 100, 'in_stock', '/images/cyberpunk-2077.jpg', JSON_ARRAY('/images/cyberpunk-1.jpg', '/images/cyberpunk-2.jpg'), '2020-12-10', 'M', @cdpr_pub_id, @cdpr_dev_id, TRUE, FALSE, 'published', 4.50, 2),
('Elden Ring', 'elden-ring', 'Rise, Tarnished, and be guided by grace to brandish the power of the Elden Ring.', 'In the Lands Between ruled by Queen Marika the Eternal, the Elden Ring, the source of the Erdtree, has been shattered. Marika\'s offspring, demigods all, claimed the shards of the Elden Ring known as the Great Runes, and the mad taint of their newfound strength triggered a war: The Shattering.', 'ER-001', 59.99, NULL, 0.00, 150, 'in_stock', '/images/elden-ring.jpg', JSON_ARRAY('/images/elden-1.jpg', '/images/elden-2.jpg'), '2022-02-25', 'M', @fromsoftware_pub_id, @fromsoftware_dev_id, TRUE, TRUE, 'published', 5.00, 1),
('FIFA 24', 'fifa-24', 'EA SPORTS FC™ 24 brings you the world of football.', 'EA SPORTS FC™ 24 is a new era for The World\'s Game: 19,000+ fully licensed players, 700+ teams, and 30+ leagues playing together in the most authentic football experience ever created.', 'FIFA24-001', 69.99, 59.99, 14.29, 200, 'in_stock', '/images/fifa-24.jpg', JSON_ARRAY('/images/fifa-1.jpg'), '2023-09-29', 'E', @ea_pub_id, @ea_dev_id, FALSE, TRUE, 'published', 0.00, 0),
('The Legend of Zelda: Tears of the Kingdom', 'zelda-teotk', 'An epic adventure across the land and skies of Hyrule.', 'The adventure is yours to create in a world fueled by your imagination. In this sequel to The Legend of Zelda: Breath of the Wild, you\'ll decide your own path through the sprawling landscapes of Hyrule and the mysterious islands floating in the vast skies above.', 'ZELDA-TOTK-001', 69.99, NULL, 0.00, 50, 'out_of_stock', '/images/zelda-totk.jpg', JSON_ARRAY('/images/zelda-1.jpg', '/images/zelda-2.jpg'), '2023-05-12', 'E10+', @nintendo_pub_id, @nintendo_dev_id, TRUE, TRUE, 'published', 0.00, 0),
('Portal 2', 'portal-2', 'Sequel to the award-winning Portal.', 'Portal 2 draws from the award-winning formula of innovative gameplay, story, and music that earned the original Portal over 70 industry accolades and created a cult following.', 'P2-001', 19.99, 4.99, 75.02, 500, 'in_stock', '/images/portal-2.jpg', JSON_ARRAY('/images/portal-1.jpg'), '2011-04-19', 'E10+', @valve_pub_id, @valve_dev_id, FALSE, FALSE, 'published', 4.00, 1);

-- Get product IDs
SET @cyberpunk_id = 1;
SET @eldenring_id = 2;
SET @fifa_id = 3;
SET @zelda_id = 4;
SET @portal_id = 5;

-- Link products to categories
INSERT INTO product_categories (product_id, category_id, is_primary) VALUES
(@cyberpunk_id, 1, TRUE), -- Action
(@cyberpunk_id, 6, FALSE), -- RPG
(@eldenring_id, 1, TRUE), -- Action
(@eldenring_id, 6, FALSE), -- RPG
(@fifa_id, 5, TRUE), -- Sports
(@zelda_id, 1, TRUE), -- Action
(@zelda_id, 2, FALSE), -- Adventure
(@portal_id, 2, TRUE); -- Adventure

-- Link products to platforms
INSERT INTO product_platforms (product_id, platform_id) VALUES
(@cyberpunk_id, 1), -- PC
(@cyberpunk_id, 2), -- PS5
(@cyberpunk_id, 3), -- Xbox
(@eldenring_id, 1), -- PC
(@eldenring_id, 2), -- PS5
(@eldenring_id, 3), -- Xbox
(@fifa_id, 1), -- PC
(@fifa_id, 2), -- PS5
(@fifa_id, 3), -- Xbox
(@zelda_id, 4), -- Switch
(@portal_id, 1); -- PC

-- Link products to tags
INSERT INTO tags (name, slug, color) VALUES
('Open World', 'open-world', '#00ff00'),
('Multiplayer', 'multiplayer', '#0000ff'),
('Singleplayer', 'singleplayer', '#ff0000'),
('Fantasy', 'fantasy', '#ff00ff');

INSERT INTO product_tags (product_id, tag_id) VALUES
(@cyberpunk_id, (SELECT id FROM tags WHERE slug = 'open-world')),
(@cyberpunk_id, (SELECT id FROM tags WHERE slug = 'singleplayer')),
(@eldenring_id, (SELECT id FROM tags WHERE slug = 'open-world')),
(@eldenring_id, (SELECT id FROM tags WHERE slug = 'singleplayer')),
(@eldenring_id, (SELECT id FROM tags WHERE slug = 'fantasy')),
(@fifa_id, (SELECT id FROM tags WHERE slug = 'multiplayer')),
(@zelda_id, (SELECT id FROM tags WHERE slug = 'open-world')),
(@zelda_id, (SELECT id FROM tags WHERE slug = 'singleplayer')),
(@zelda_id, (SELECT id FROM tags WHERE slug = 'fantasy')),
(@portal_id, (SELECT id FROM tags WHERE slug = 'singleplayer'));

-- Insert related products
INSERT INTO related_products (product_id, related_product_id, relation_type) VALUES
(@cyberpunk_id, @eldenring_id, 'similar'),
(@eldenring_id, @cyberpunk_id, 'similar'),
(@portal_id, @cyberpunk_id, 'cross_sell');

-- Insert product keys
INSERT INTO product_keys (product_id, license_key, is_used) VALUES
(@cyberpunk_id, 'CYBER-KEY-ABCD-EFGH-1234', FALSE),
(@cyberpunk_id, 'CYBER-KEY-IJKL-MNOP-5678', FALSE),
(@cyberpunk_id, 'CYBER-KEY-QRST-UVWX-9012', TRUE),
(@eldenring_id, 'ELDEN-RING-ABCD-EFGH-3456', FALSE),
(@eldenring_id, 'ELDEN-RING-IJKL-MNOP-7890', FALSE),
(@portal_id, 'PORTAL-2-KEY-ABCD-1122', FALSE),
(@portal_id, 'PORTAL-2-KEY-IJKL-3344', TRUE);

-- ===== Orders & Sales =====
-- Insert coupons
INSERT INTO coupons (code, type, value, minimum_amount, usage_limit, per_user_limit, start_date, end_date) VALUES
('WELCOME10', 'percentage', 10.00, 20.00, 100, 1, '2023-01-01', '2024-12-31'),
('SUMMER5', 'fixed_amount', 5.00, 0.00, 50, 1, '2024-06-01', '2024-08-31');

-- Insert items into cart for some users
INSERT INTO cart (user_id, product_id, quantity, price) VALUES
(@john_id, @cyberpunk_id, 1, 29.99),
(@sara_id, @eldenring_id, 1, 59.99),
(@sara_id, @portal_id, 2, 4.99);

-- Insert orders
INSERT INTO orders (order_number, user_id, status, payment_status, payment_method, subtotal, discount_amount, tax_amount, total_amount, billing_address, shipping_address) VALUES
('ORD-20231027-001', @john_id, 'delivered', 'paid', 'credit_card', 29.99, 0.00, 2.40, 32.39,
 JSON_OBJECT('first_name', 'John', 'last_name', 'Doe', 'address', '123 Main St', 'city', 'Los Angeles', 'country', 'USA'),
 JSON_OBJECT('first_name', 'John', 'last_name', 'Doe', 'address', '123 Main St', 'city', 'Los Angeles', 'country', 'USA')),
('ORD-20231028-002', @jane_id, 'processing', 'paid', 'paypal', 69.99, 5.00, 5.20, 70.19,
 JSON_OBJECT('first_name', 'Jane', 'last_name', 'Smith', 'address', '456 Oxford St', 'city', 'London', 'country', 'UK'),
 JSON_OBJECT('first_name', 'Jane', 'last_name', 'Smith', 'address', '456 Oxford St', 'city', 'London', 'country', 'UK')),
('ORD-20231029-003', @peter_id, 'cancelled', 'failed', 'bank_transfer', 59.99, 0.00, 0.00, 59.99,
 JSON_OBJECT('first_name', 'Peter', 'last_name', 'Jones', 'address', '789 Queen St W', 'city', 'Toronto', 'country', 'Canada'),
 JSON_OBJECT('first_name', 'Peter', 'last_name', 'Jones', 'address', '789 Queen St W', 'city', 'Toronto', 'country', 'Canada'));

-- Get order IDs
SET @order1_id = 1;
SET @order2_id = 2;
SET @order3_id = 3;

-- Insert order items
INSERT INTO order_items (order_id, product_id, quantity, price, total) VALUES
(@order1_id, @cyberpunk_id, 1, 29.99, 29.99),
(@order2_id, @zelda_id, 1, 69.99, 69.99),
(@order3_id, @eldenring_id, 1, 59.99, 59.99);

-- Link product keys to delivered order items
UPDATE product_keys SET order_item_id = 1, is_used = TRUE WHERE license_key = 'CYBER-KEY-QRST-UVWX-9012';

-- Insert order status history
INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES
(@order1_id, 'pending', 'Order received', @admin_id),
(@order1_id, 'processing', 'Order is being prepared', @manager_id),
(@order1_id, 'shipped', 'Order has been shipped', @manager_id),
(@order1_id, 'delivered', 'Order delivered successfully', @admin_id),
(@order3_id, 'pending', 'Order received', @admin_id),
(@order3_id, 'cancelled', 'Payment failed, order cancelled', @manager_id);

-- ===== Reviews & Feedback =====
-- Insert product reviews
INSERT INTO product_reviews (product_id, user_id, rating, title, review, is_verified_purchase, is_approved) VALUES
(@cyberpunk_id, @john_id, 5, 'Amazing Game!', 'Night City is breathtaking. The story is deep and engaging. A must-play RPG!', TRUE, TRUE),
(@cyberpunk_id, @jane_id, 4, 'Good, but had bugs at launch', 'The game is much better now. I enjoyed my playthrough, though it\'s not perfect.', FALSE, TRUE),
(@eldenring_id, @peter_id, 5, 'Masterpiece!', 'FromSoftware has outdone themselves. The world is vast and full of secrets.', FALSE, TRUE),
(@portal_id, @sara_id, 4, 'Classic Puzzle Game', 'Funny, clever, and challenging. The co-op mode is a blast!', FALSE, TRUE);

-- Insert review replies
INSERT INTO review_replies (review_id, user_id, reply, is_admin_reply) VALUES
(1, @manager_id, 'Thank you for your review! We are glad you enjoyed it.', TRUE);

-- Insert review helpfulness
INSERT INTO review_helpfulness (review_id, user_id, is_helpful) VALUES
(1, @jane_id, TRUE),
(1, @peter_id, TRUE),
(2, @john_id, FALSE);

-- ===== Communication & Support =====
-- Insert contact messages
INSERT INTO contact_messages (name, email, subject, message, status, priority) VALUES
('Alice Wonder', 'alice@example.com', 'Issue with a game key', 'I bought a key but it says it\'s already used.', 'new', 'high'),
('Bob Builder', 'bob@example.com', 'Pre-order question', 'When will the next DLC be available for pre-order?', 'resolved', 'low');

-- Insert newsletter subscribers
INSERT INTO newsletter_subscribers (email, name, status) VALUES
('newsletter.fan@example.com', 'Newsletter Fan', 'active'),
('another.fan@example.com', 'Another Fan', 'active');

-- ===== Content Management =====
-- Insert pages
INSERT INTO pages (title, slug, content, status, created_by) VALUES
('About Us', 'about-us', '<p>We are a dedicated team of gamers bringing you the best digital games at the best prices.</p>', 'published', @admin_id),
('Terms of Service', 'terms-of-service', '<p>These are the terms and conditions for using our website...</p>', 'published', @admin_id),
('Contact', 'contact', '<p>Get in touch with us via the contact form.</p>', 'published', @manager_id);

-- Insert menus
INSERT INTO menus (name, location, items) VALUES
('Main Menu', 'header', JSON_ARRAY(
    JSON_OBJECT('name', 'Home', 'url', '/'),
    JSON_OBJECT('name', 'Shop', 'url', '/products'),
    JSON_OBJECT('name', 'About Us', 'url', '/pages/about-us'),
    JSON_OBJECT('name', 'Contact', 'url', '/pages/contact')
)),
('Footer Menu', 'footer', JSON_ARRAY(
    JSON_OBJECT('name', 'Terms of Service', 'url', '/pages/terms-of-service'),
    JSON_OBJECT('name', 'Privacy Policy', 'url', '/pages/privacy-policy')
));

-- Insert banners
INSERT INTO banners (title, image, link, position, sort_order, is_active) VALUES
('Cyberpunk 2077 Sale', '/images/banners/cyberpunk-sale.jpg', '/products/cyberpunk-2077', 'homepage_slider', 1, TRUE),
('New Release: Zelda TOTK', '/images/banners/zelda-totk.jpg', '/products/zelda-teotk', 'homepage_slider', 2, TRUE);

-- ===== New Tables =====
-- Insert wishlists
INSERT INTO wishlists (user_id, product_id) VALUES
(@jane_id, @cyberpunk_id),
(@peter_id, @fifa_id),
(@sara_id, @zelda_id);

-- Insert price history
INSERT INTO price_history (product_id, old_price, new_price, changed_by) VALUES
(@cyberpunk_id, 59.99, 29.99, @manager_id),
(@portal_id, 19.99, 4.99, @manager_id);

-- ===== Settings & Configuration Tables =====
-- Insert activity logs
INSERT INTO activity_logs (user_id, action, description, model_type, model_id, ip_address) VALUES
(@john_id, 'login', 'User logged in', 'App\\Models\\User', @john_id, '127.0.0.1'),
(@manager_id, 'update', 'Updated price for Cyberpunk 2077', 'App\\Models\\Product', @cyberpunk_id, '127.0.0.1'),
(@jane_id, 'place_order', 'Placed an order', 'App\\Models\\Order', @order2_id, '127.0.0.1');

-- Insert user sessions
INSERT INTO user_sessions (id, user_id, ip_address, last_activity) VALUES
('session_abc123', @john_id, '127.0.0.1', NOW()),
('session_def456', @jane_id, '127.0.0.1', NOW());

-- ===== Statistics & Reporting Tables =====
-- Insert page views
INSERT INTO page_views (url, user_id, ip_address, device_type, browser, created_at) VALUES
('/', @john_id, '127.0.0.1', 'desktop', 'Chrome', NOW()),
('/products/cyberpunk-2077', @jane_id, '127.0.0.1', 'mobile', 'Safari', NOW()),
('/cart', @sara_id, '127.0.0.1', 'desktop', 'Firefox', NOW());

-- Insert daily sales stats
INSERT INTO daily_sales_stats (date, orders_count, revenue, new_customers, returning_customers, products_sold, avg_order_value) VALUES
('2023-10-27', 1, 32.39, 1, 0, 1, 32.39),
('2023-10-28', 1, 70.19, 1, 0, 1, 70.19),
('2023-10-29', 0, 0.00, 0, 0, 0, 0.00);