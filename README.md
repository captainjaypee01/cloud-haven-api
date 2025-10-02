# Cloud Haven API

A comprehensive Laravel-based REST API for the Cloud Haven Resort Booking System, featuring advanced booking management, payment processing, and resort administration.

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+
- Redis 6.0+

### Installation
```bash
# Clone the repository
git clone <repository-url>
cd cloud-haven-api

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start development server
php artisan serve
```

### Docker Setup
```bash
# Using Docker Compose
docker-compose up -d

# Or using the development script
composer run dev
```

## 🏗️ Technology Stack

- **Framework**: Laravel 12.0
- **PHP Version**: 8.2+
- **Database**: MySQL 8.0+
- **Cache**: Redis 6.0+
- **Authentication**: Clerk Backend PHP 0.2.0
- **File Storage**: Cloudinary 3.0
- **Email**: Mailgun 4.3 with failover
- **PDF**: DomPDF 3.1
- **Testing**: Pest PHP 3.0

## 📋 Core Features

### 🏨 **Resort Management**
- **Room Types**: Multiple room categories with pricing
- **Room Units**: Individual room management
- **Amenities**: Comprehensive amenity system
- **Images**: Cloudinary-based image management
- **Pricing**: Dynamic pricing with seasonal adjustments

### 📅 **Booking System**
- **Overnight Bookings**: Traditional hotel reservations
- **Day Tour Bookings**: Single-day experiences
- **Walk-in Bookings**: Admin-created bookings
- **Availability**: Real-time room availability checking
- **Booking Holds**: Configurable reservation periods
- **Reference Numbers**: Unique booking identifiers

### 💳 **Payment Processing**
- **Proof of Payment**: Upload-based verification
- **Downpayment**: Flexible payment terms (50% default)
- **Payment Tracking**: Multiple attempts and failure handling
- **Admin Review**: Staff approval/rejection system
- **Grace Periods**: Time extensions for resubmission

### 🍽️ **Meal Programs**
- **Dynamic Pricing**: Date-based meal program pricing
- **Meal Options**: Lunch and afternoon snack selections
- **Pricing Tiers**: Multiple pricing levels
- **Calendar Overrides**: Special pricing for dates
- **Availability**: Real-time meal program checking

### 👥 **User Management**
- **Role-based Access**: Staff, Admin, Superadmin roles
- **Clerk Integration**: Modern authentication with webhooks
- **User Profiles**: Guest information management
- **Booking History**: Complete booking tracking

### 📊 **Admin Features**
- **Booking Management**: Complete booking lifecycle
- **Calendar Views**: Visual booking and availability
- **Payment Tracking**: Payment status and proof management
- **Analytics**: Booking and revenue reporting
- **User Management**: Staff and user administration

## 🏗️ Architecture

### Service Repository Pattern
```
app/
├── Actions/            # Business logic actions
├── Contracts/          # Service interfaces
├── DTO/                # Data Transfer Objects
├── Enums/              # Application enums
├── Http/Controllers/    # API controllers
├── Models/              # Eloquent models
├── Repositories/        # Data access layer
├── Services/            # Business logic services
└── Utils/               # Utility classes
```

### API Design
- **RESTful API**: Standard HTTP methods and status codes
- **Resource/Collection**: Consistent response patterns
- **API Versioning**: v1 API with future version support
- **Error Handling**: Comprehensive error responses

## 🔧 Configuration

### Environment Variables
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cloud_haven
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Clerk Authentication
CLERK_SECRET_KEY=your_clerk_secret
CLERK_WEBHOOK_SECRET=your_webhook_secret

# Cloudinary
CLOUDINARY_URL=cloudinary://api_key:api_secret@cloud_name

# Mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=your_mailgun_key
MAILGUN_ENDPOINT=api.mailgun.net

# Booking Configuration
BOOKING_RESERVATION_HOLD_HOURS=2
BOOKING_SCHEDULER_INTERVAL_MINUTES=30
BOOKING_PROOF_REJECTION_GRACE_PERIOD_DAYS=2
BOOKING_DOWNPAYMENT_PERCENT=0.5
```

## 📡 API Endpoints

### Base URL
- **Development**: `http://localhost:8000/api/v1`
- **Production**: `https://api.cloudhaven.com/v1`

### Authentication
All protected routes require Clerk JWT authentication:
```bash
Authorization: Bearer <clerk_jwt_token>
```

### Public Endpoints
```bash
# Rooms
GET    /rooms                    # List all rooms
GET    /rooms/{slug}             # Room details
POST   /rooms/availability       # Check availability
GET    /rooms/featured           # Featured rooms

# Day Tours
GET    /day-tours/availability   # Day tour availability

# Bookings
POST   /bookings                 # Create booking
GET    /bookings/ref/{reference} # Get booking by reference

# Meals
GET    /meal-prices              # Get meal prices
GET    /meals/availability       # Meal availability
POST   /meals/quote               # Get meal quote
```

### Protected Endpoints
```bash
# User Bookings
GET    /my-bookings              # User's bookings

# Payments
POST   /bookings/ref/{reference}/pay                    # Process payment
POST   /bookings/ref/{reference}/payments/{id}/proof     # Upload proof
```

### Admin Endpoints
```bash
# Booking Management
GET    /admin/bookings           # List all bookings
GET    /admin/bookings/calendar  # Booking calendar
POST   /admin/bookings/walk-in   # Create walk-in booking
PUT    /admin/bookings/{id}/cancel # Cancel booking
DELETE /admin/bookings/{id}      # Delete booking

# Room Management
GET    /admin/rooms              # List rooms
POST   /admin/rooms              # Create room
PUT    /admin/rooms/{id}         # Update room
DELETE /admin/rooms/{id}         # Delete room

# User Management
GET    /admin/users              # List users
POST   /admin/users              # Create user
PUT    /admin/users/{id}         # Update user
DELETE /admin/users/{id}         # Delete user
```

## 🔐 Security

### Authentication & Authorization
- **Clerk JWT**: Secure token-based authentication
- **Role-based Access**: Middleware for different user roles
- **Webhook Security**: Clerk webhook verification
- **CSRF Protection**: Laravel CSRF token protection

### Data Protection
- **Input Validation**: Laravel Form Request validation
- **SQL Injection Prevention**: Eloquent ORM protection
- **XSS Protection**: Input sanitization
- **Rate Limiting**: API rate limiting for public endpoints

## 📊 Database Schema

### Core Tables
- **bookings**: Main booking records
- **booking_rooms**: Room selections per booking
- **rooms**: Room type definitions
- **room_units**: Individual room instances
- **users**: User accounts (Clerk integration)
- **payments**: Payment records and proofs
- **meal_programs**: Meal program definitions
- **amenities**: Resort amenities

### Relationships
- Bookings have many BookingRooms
- Rooms have many RoomUnits
- Users have many Bookings
- Bookings belong to Users

## 🧪 Testing

### Test Setup
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

### Testing Strategy
- **Unit Tests**: Individual service and model testing
- **Feature Tests**: API endpoint testing
- **Integration Tests**: Cross-service testing
- **Database Tests**: Model relationship testing

## 🚀 Performance

### Optimization Strategies
- **Database Indexing**: Optimized query performance
- **Redis Caching**: Session and data caching
- **Queue Processing**: Background job processing
- **API Optimization**: Efficient response structures

### Monitoring
- **Query Logging**: Database query performance
- **Cache Hit Rates**: Redis cache effectiveness
- **Queue Performance**: Job processing metrics
- **API Response Times**: Endpoint performance

## 📧 Email System

### Mailgun Integration
- **Transactional Emails**: Booking confirmations
- **Admin Notifications**: Payment and booking alerts
- **Failover System**: Log-based email handling
- **Template System**: Dynamic email templates

### Email Types
- **Booking Confirmation**: New booking notifications
- **Payment Proof**: Payment verification emails
- **Booking Updates**: Status change notifications
- **Admin Alerts**: System notifications

## 🔄 Queue System

### Background Jobs
- **Email Processing**: Queued email sending
- **Booking Cleanup**: Expired booking removal
- **Report Generation**: Analytics processing
- **Image Processing**: Cloudinary optimization

### Queue Configuration
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## 📁 File Management

### Cloudinary Integration
- **Image Upload**: Secure file upload
- **Image Optimization**: Automatic resizing
- **CDN Delivery**: Fast image delivery
- **Backup Storage**: File redundancy

### File Types
- **Room Images**: Property photos
- **Payment Proofs**: Transaction documents
- **User Avatars**: Profile pictures
- **Documents**: Booking confirmations

## 🚀 Deployment

### Production Setup
   ```bash
# Install dependencies
composer install --optimize-autoloader --no-dev

# Cache configuration
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

# Run migrations
php artisan migrate --force

# Start queue worker
   php artisan queue:work
   ```

### Docker Deployment
```dockerfile
FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www
```

## 📈 Monitoring & Logging

### Application Logs
- **Laravel Logs**: Application error logging
- **Query Logs**: Database query performance
- **Queue Logs**: Background job processing
- **API Logs**: Request/response logging

### Performance Monitoring
- **Database Queries**: Query optimization
- **Cache Performance**: Redis hit rates
- **Queue Processing**: Job completion rates
- **API Response Times**: Endpoint performance

## 🤝 Contributing

### Development Workflow
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

### Code Standards
- **PSR-12**: PHP coding standards
- **Laravel Conventions**: Framework best practices
- **SOLID Principles**: Clean architecture
- **Testing**: Comprehensive test coverage

## 📄 License

This project is proprietary software. All rights reserved.

## 📞 Support

For technical support or questions:
- **Email**: support@cloudhaven.com
- **Documentation**: [Internal Wiki]
- **Issues**: [GitHub Issues]

---

**Cloud Haven API** - Built with ❤️ for modern resort management