Fitness Buddy
Fitness Buddy is a web application designed to help users stay connected and motivated on their fitness journey. The app features a user-friendly interface to create posts, interact with others, track fitness goals, and connect with fellow fitness enthusiasts. The app has a simple, Reddit-like interface for sharing fitness tips, progress, and motivation.

Features
  User Authentication: Users can register, log in, and manage their profiles.
  Post Creation: Users can create posts, share their fitness progress, or provide tips.
  Post Interaction: Users can view recent posts, including the poster's username and time of creation.
  No Posts Yet: If no posts exist, the user will be informed with a message saying "No posts yet :(".
  Post Viewing: Clicking on a post will allow users to view more details.

Pages
  1. Home Page (index.php)
  The home page displays all the recent posts made by users. Each post shows:
  
  The content of the post.
  The username of the person who created the post.
  The time when the post was created.
  A button that allows users to view the post in more detail.
  If no posts have been created yet, the message "No posts yet :(" will be shown.
  
  Additionally, if the user is logged in, the home page provides a link to create a new post.
  
  2. Create Post Page (create_post.php)
  This page allows users to create a new post. The user is prompted to provide content for the post. Once the user submits the form, the post is stored in the database and displayed on the home page.
  Content: Users can share their thoughts, progress, or tips regarding fitness.
  Session Handling: The user must be logged in to create a post. If the user is not logged in, they will be prompted to log in first.
  
  3. Post View Page (post.php)
  When users click on the "View Post" button from the home page, they are taken to this page where they can see the full content of the post along with more details.
  
  4. Login and Registration Pages
    The application includes login and registration features, which are essential for users to create and manage their posts. These features are handled by the following files:
    api_login.php: Handles the login logic. It verifies user credentials and establishes a session for authenticated users.
    api_register.php: Handles the registration logic. It allows new users to sign up for the platform by providing necessary details, such as username, password, etc.
    login.php: The front-end page where users can input their credentials (username and password) to log in.
    register.php: The front-end page where new users can sign up for an account by providing their username, email, and password.
    Once users are logged in, they are granted access to create posts and interact with the content. The login page is displayed for users who are not authenticated, and the register page is available for new         users to create an account.
  
  Post Content: Displays the full content of the selected post.
  User Info: Displays the username of the person who created the post.
  Date/Time: Shows when the post was made.
  4. Login/Registration (Not Fully Implemented in Code)
  Though the login and registration functionalities are typically handled in fitness apps, they should ideally allow users to sign up for the platform and log in to manage their posts.
  
  Setup Instructions
  Requirements
  PHP 7.4 or higher
  MySQL Database
  XAMPP or equivalent local server environment
  
  Steps to Run the Application
  Clone the Repository

  Edit
  git clone https://github.com/anhad-dhiman/Fitness-Buddy.git


  Database Setup
    Create a database in MySQL named fitness_buddy.
    Import the provided SQL file to set up the necessary tables like users, posts, etc.
    Update Database Configuration

In the db.php file, ensure that the database connection details (username, password) are correctly set.
Start Your Local Server

Run your server using XAMPP or any other server software that supports PHP and MySQL.
Access the Application

Open a browser and navigate to http://localhost/fitnessBuddy to start using the app.
