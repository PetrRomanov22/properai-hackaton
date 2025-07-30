# ProperAI - 3D Property Visualization Platform

🏠 **Advanced property visualization platform with real-world 3D mapping**

ProperAI is a comprehensive web application that allows users to create, manage, and share interactive 3D property visualizations using Google Maps integration. Perfect for real estate professionals, property developers, and anyone looking to showcase properties in an immersive 3D environment.

## 🌟 Features

### 🔐 User Management
- **User Registration & Authentication**: Email-based registration with secure password hashing
- **Google OAuth Integration**: Quick login/registration with Google accounts
- **Session Management**: Secure session-based authentication
- **Account Management**: Update profile information and manage settings
- **reCAPTCHA Protection**: Spam protection during registration

### 🏗️ Project Management  
- **Property Projects**: Create and manage multiple property projects
- **Detailed Property Information**: 
  - Address, city, country with geocoding
  - Property type (apartment, house, commercial, land)
  - Size, bedrooms, floor number
  - Price with multi-currency support (USD, EUR, GBP, JPY, CNY)
  - Rich text descriptions
- **Project Wizard**: Step-by-step guided project creation
- **Bulk Operations**: Delete and manage multiple projects

### 🗺️ 3D Map Integration
- **Interactive 3D Maps**: Powered by Google Maps API
- **Camera Controls**: Adjustable altitude, tilt, heading, and range
- **Viewpoint Management**: Save and restore camera positions
- **Real-time Updates**: Live editing of 3D parameters
- **Map Animations**: Create animated tours of properties

### 📸 Media Management
- **Photo Gallery**: Upload and manage property photos
- **Lightbox Viewer**: Full-screen photo viewing experience
- **Secure File Serving**: Protected file access with user authentication
- **Video Support**: Upload and manage property videos
- **Image Optimization**: Automatic image processing

### 🎯 3D Model Integration
- **3D Model Upload**: Support for GLB/GLTF format 3D models
- **Model Positioning**: Precise placement on 3D maps
- **Scale & Rotation**: Adjust model size and orientation
- **Multiple Models**: Manage multiple models per project
- **Model Replacement**: Easy model updates and replacements

### 🌐 Project Sharing
- **Public Sharing**: Generate shareable links for properties
- **Share Templates**: Customizable sharing page layouts
- **View Tracking**: Monitor project view counts
- **SEO Optimized**: Search engine friendly sharing pages

### 💳 Subscription System
- **Freemium Model**: Free tier with limited API requests
- **Usage Tracking**: Monitor API request consumption
- **Subscription Plans**: Multiple pricing tiers
- **Payment Integration**: Automated billing system
- **Usage Limits**: Enforce API request limits per plan

### 🔧 Google API Integration
- **Automatic API Key Generation**: Create Google Maps API keys for users
- **Service Account Integration**: Secure Google Cloud integration
- **API Usage Monitoring**: Track and manage API consumption
- **Rate Limiting**: Prevent API abuse

## 🛠️ Technology Stack

### Backend
- **PHP 7.4+**: Server-side logic and API endpoints
- **MySQL**: Primary database for data storage
- **Session Management**: Secure user session handling
- **File Upload**: Secure file handling with validation

### Frontend
- **HTML5/CSS3**: Modern responsive design
- **JavaScript (ES6+)**: Interactive user interface
- **Google Maps JavaScript API**: 3D map rendering
- **AJAX**: Asynchronous data loading
- **Responsive Design**: Mobile-friendly interface

### External Integrations
- **Google Maps API**: 3D mapping and geocoding
- **Google OAuth 2.0**: Social authentication
- **Google Cloud APIs**: API key management
- **reCAPTCHA v2**: Bot protection
- **PHPMailer**: Email notifications

### Security
- **Password Hashing**: Secure bcrypt password storage
- **CSRF Protection**: Cross-site request forgery prevention
- **Input Validation**: SQL injection and XSS protection
- **File Security**: Secure file upload and serving
- **Session Security**: Secure session management

## 📁 Project Structure

```
hostinger/
 public_html/
├── account.php              # User dashboard and account management
├── login.php                # User authentication
├── register.php             # User registration
├── config.php               # Application configuration
├── db.php                   # Database connection
├── welcome.php              # Landing page
├── project_create_wizard.php # Guided project creation
├── create_project.php       # Project creation handler
├── delete_project.php       # Project deletion
├── update_account.php       # Account update handler
├── google_login.php         # Google OAuth initialization
├── google_callback.php      # Google OAuth callback
├── google_api_service.php   # Google API integration
├── email_service.php        # Email handling
├── fetch_api_usage.php      # API usage tracking
├── update_subscriptions.php # Subscription management
├── check_subscription.php   # Subscription validation
├── project/
│   ├── project.php          # Project details and editing
│   ├── project_map.php      # 3D map editor
│   ├── project_map_animation.php # Animation creator
│   ├── project_share.php    # Public project sharing
│   ├── upload_photo.php     # Photo upload handler
│   ├── upload_video.php     # Video upload handler
│   ├── upload_model.php     # 3D model upload
│   ├── serve_photo.php      # Secure photo serving
│   ├── serve_video.php      # Secure video serving
│   ├── serve_model.php      # Secure model serving
│   ├── delete_photo.php     # Photo deletion
│   ├── delete_video.php     # Video deletion
│   ├── delete_model.php     # Model deletion
│   └── users/               # User file storage
│── keys/                    # Google service account keys
│── vendor/                  # Composer dependencies
└── phpmailer/               # Email library
└── database_info.sql            # Database schema
```

## 🗄️ Database Schema

### Core Tables
- **users**: User accounts, authentication, and subscription info
- **projects**: Property project details and 3D parameters
- **models**: 3D model files and positioning data
- **viewpoints**: Saved camera positions for projects
- **subscriptions**: User subscription and billing data
- **subscription_plans**: Available pricing plans
- **shared_pages**: Public project sharing configuration
- **project_videos**: Video file management

## 🚀 Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependencies)
- Google Cloud Platform account
- Google Maps API key

### Installation Steps

1. **Clone/Upload the project**
   ```bash
   # Upload the hostinger folder to your web server
   ```

2. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u username -p database_name < database_info.sql
   ```

3. **Environment Configuration**
   ```bash
   # Copy environment template
   cp env.example .env
   
   # Configure your environment variables:
   # - Database credentials
   # - Google OAuth keys
   # - Google Cloud project ID
   # - reCAPTCHA keys
   # - Email settings
   ```

4. **Install Dependencies**
   ```bash
   composer install
   ```

5. **Configure Google Services**
   - Set up Google Cloud Console project
   - Enable Maps JavaScript API, Geocoding API
   - Create service account and download JSON key
   - Place service account key in `keys/` directory
   - Configure OAuth 2.0 credentials

6. **File Permissions**
   ```bash
   # Set proper permissions for upload directories
   chmod 755 public_html/project/users/
   ```

### Environment Variables

Create a `.env` file or configure these in your hosting environment:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=your_database_user
DB_PASS=your_database_password  
DB_NAME=your_database_name

# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/google_callback.php

# Google Cloud
GOOGLE_CLOUD_PROJECT_ID=your-google-cloud-project-id

# reCAPTCHA
RECAPTCHA_SITE_KEY=your-recaptcha-site-key
RECAPTCHA_SECRET_KEY=your-recaptcha-secret-key

# Email Configuration
EMAIL_FROM_ADDRESS=info@yourdomain.com
EMAIL_FROM_NAME=Your App Name
EMAIL_SUPPORT_ADDRESS=support@yourdomain.com
```

## 📖 Usage Guide

### For Users
1. **Registration**: Sign up with email or Google account
2. **Create Project**: Use the wizard to add property details
3. **Upload Media**: Add photos and 3D models to projects
4. **Configure 3D View**: Adjust camera angles and viewpoints
5. **Share Projects**: Generate public links for property viewing
6. **Manage Subscription**: Upgrade plans for more API requests

### For Developers
- **API Integration**: Extend with custom API endpoints
- **Theme Customization**: Modify CSS for branding
- **Plugin Development**: Add custom functionality
- **Database Extensions**: Add custom tables and fields

## 🔒 Security Features

- **SQL Injection Protection**: Prepared statements throughout
- **XSS Prevention**: Input sanitization and output encoding
- **CSRF Protection**: Form tokens and validation
- **File Upload Security**: Type validation and secure storage
- **Password Security**: bcrypt hashing with salt
- **Session Security**: Secure session configuration
- **API Rate Limiting**: Prevent abuse of external APIs

## 🤝 Contributing

This project appears to be a commercial application. 

## 📝 License

This project is licensed under the **ProperAI Non-Commercial License**. 

**Key License Terms:**
- ✅ **Allowed**: Personal use, educational use, research, modification, distribution
- ❌ **Prohibited**: Commercial use, selling, revenue generation, for-profit business use
- 📋 **Requirements**: Attribution required, derivative works must use same license

For **commercial licensing**, please contact: info@properai.pro

See the [LICENSE](LICENSE) file for complete terms and conditions.

## 🆘 Support

For technical support or questions:
- Email: info@properai.pro
- Website: https://properai.pro/

## 🔮 Future Enhancements

Potential areas for expansion:
- Mobile app development
- VR/AR integration
- Advanced analytics dashboard
- Multi-language support
- API rate limiting improvements
- Enhanced 3D model formats
- Automated property valuation
- CRM integration
- Advanced sharing options

---

**ProperAI** - Transforming property visualization with cutting-edge 3D technology 🏠✨