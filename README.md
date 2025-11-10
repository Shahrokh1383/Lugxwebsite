

# Lugxwebsite - A Complete E-commerce Platform for Gaming


A full-featured e-commerce website for selling digital games, built from scratch with PHP, JavaScript, HTML, CSS, and Bootstrap 5. This project implements a custom MVC architecture with PDO for database connectivity, providing a secure and scalable solution for an online gaming store.

## 🚀 Features

- **User Authentication System**
  - Registration, login, logout
  - Email verification
  - Password reset functionality
  - Secure session management

- **Product Management**
  - Product catalog with categories, platforms, publishers, and developers
  - Advanced search and filtering
  - Product details and reviews
  - Product recommendations

- **Shopping Cart & Wishlist**
  - Add/remove items from cart
  - Update quantities
  - Apply discount coupons
  - Save products for later

- **Order Management**
  - Complete checkout process
  - Order history and tracking
  - Digital key delivery system
  - Multiple payment methods

- **Admin Panel**
  - Dashboard with statistics
  - User management
  - Product and inventory management
  - Order processing
  - Content management
  - Review moderation

- **Additional Features**
  - User profiles with address management
  - Newsletter subscription
  - Contact form
  - Responsive design
  - Security features (CSRF protection, input validation, etc.)

## 🛠️ Technologies Used

- **Backend**: PHP 7.4+ (Raw PHP, no framework)
- **Database**: MySQL with PDO
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Bootstrap 5
- **Security**: Custom security services for CSRF protection, input validation, password hashing
- **Architecture**: Custom MVC pattern with services and middleware

## 📋 Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Composer (for dependency management)

## 🚀 Installation & Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/Shahrokh1383/Lugxwebsite.git
   ```

2. **Database Setup**
   - Create a new database in phpMyAdmin
   - Import the `lugxwebsite_db.sql` file to set up the database structure and name the database `lugxwebsite_db`
   - Optionally, import `seed.sql` to populate the database with sample data

3. **Configuration**
   - Copy the `.env.example` file to `.env` (if available) and configure your database settings
   - Ensure your server is configured to listen on port 8080

4. **Access the Application**
   - User Interface: `http://localhost:8080/Lugxwebsite/`
   - Admin Panel: `http://localhost:8080/Lugxwebsite/public/frontend/admin/admin_login.html`

## 📁 Project Structure

```
Lugxwebsite/
├── .env                     # Environment variables
├── .htaccess                # Apache configuration for routing
├── composer.json            # PHP dependencies
├── db.sql                   # Database schema
├── seed.sql                 # Sample data (optional)
├── app/                     # Application core
│   ├── config/              # Configuration files
│   ├── controllers/         # MVC controllers
│   │   ├── admin/           # Admin panel controllers
│   │   └── api/             # API controllers
│   ├── core/                # Core MVC classes
│   ├── middlewares/         # Request middlewares
│   ├── models/              # Data models
│   ├── routes/              # Route definitions
│   └── services/            # Business logic services
├── public/                  # Publicly accessible files
│   ├── index.php            # Front controller
│   ├── assets/              # CSS, JS, images
│   └── frontend/            # HTML templates
└── views/                   # PHP views for emails and errors
```

## 🏗️ Architecture

This project follows a custom MVC (Model-View-Controller) architecture with additional components:

- **Models**: Handle data access and business logic for database entities
- **Views**: HTML templates with minimal PHP for rendering
- **Controllers**: Process HTTP requests and coordinate between models and views
- **Services**: Contain business logic that doesn't fit in models
- **Middlewares**: Provide request filtering (authentication, CSRF protection, etc.)
- **Routes**: Define URL patterns and map them to controller methods

### Security Implementation

The application implements several security measures:

- **CSRF Protection**: Tokens generated for forms to prevent cross-site request forgery
- **Input Validation**: Comprehensive validation service for sanitizing user input
- **Password Security**: Passwords are hashed using Argon2ID algorithm
- **SQL Injection Prevention**: All database queries use prepared statements with PDO
- **Session Security**: Secure session management with proper configuration

## 📊 Development Process

The project was developed through 15 distinct stages:

1. **Project Setup & MVC Foundation**
2. **User Authentication & Email Verification**
3. **Frontend Authentication Implementation**
4. **User Profile & Address Management**
5. **Frontend Profile & Address Implementation**
6. **Product Management Backend**
7. **Frontend Product Display**
8. **Shopping Cart & Wishlist Backend**
9. **Frontend Cart & Wishlist Implementation**
10. **Order Process & Payment Backend**
11. **Frontend Order Process Implementation**
12. **Review & Rating System**
13. **Admin Panel Foundation**
14. **Admin Content Management**
15. **Settings, Reports & Optimization**

## 🔑 API Documentation

### Authentication Endpoints

- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `POST /api/auth/forgot-password` - Password reset request
- `POST /api/auth/reset-password` - Password reset confirmation

### Product Endpoints

- `GET /api/products` - Get products with filtering and pagination
- `GET /api/products/{id}` - Get product details
- `GET /api/categories` - Get all categories
- `GET /api/platforms` - Get all platforms

### Cart & Wishlist Endpoints

- `GET /api/cart` - Get cart contents
- `POST /api/cart/add` - Add item to cart
- `PUT /api/cart/update` - Update cart item
- `DELETE /api/cart/remove` - Remove item from cart
- `GET /api/wishlist` - Get wishlist
- `POST /api/wishlist/add` - Add item to wishlist
- `DELETE /api/wishlist/remove` - Remove item from wishlist

### Order Endpoints

- `POST /api/orders` - Create new order
- `GET /api/orders` - Get user orders
- `GET /api/orders/{id}` - Get order details
- `GET /api/orders/{id}/keys` - Get product keys for completed order

### Admin Endpoints

All admin endpoints are prefixed with `/api/admin/` and require admin authentication.

## 🎯 Admin Credentials

For testing purposes, you can use these credentials:
- Email: `admin@example.com`
- Password: `admin123`

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a pull request or open an issue for any bugs or feature requests.

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Bootstrap for the UI framework
- PHPMailer for email functionality
- All contributors and supporters of this project

---

**Note**: This project was built from scratch without using any PHP framework, demonstrating a deep understanding of PHP, MVC architecture, security best practices, and full-stack web development.