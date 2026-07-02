<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Change the title in each page -->
    <title><?php echo isset($pageTitle) ? $pageTitle : "Bodhi Homeopathy"; ?></title>

    <link rel="stylesheet" href="css/nstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<div class="side-social">
    <a href="#" class="whatsapp"><i class="fab fa-whatsapp"></i></a>
    <a href="#" class="facebook"><i class="fab fa-facebook-f"></i></a>
    <a href="#" class="instagram"><i class="fab fa-instagram"></i></a>
</div>

<header style="border-top:3px solid #657266;">

<div class="top-nav">

    <div class="logo">
        <img src="images/lgo.png" alt="Bodhi Logo">
    </div>

    <input type="checkbox" id="menu-btn">

    <label for="menu-btn" class="menu-toggle">
        <i class="fas fa-bars"></i>
    </label>

    <nav>
        <ul>

            <li><a href="index.php">HOME</a></li>

            <li><a href="about.php">ABOUT US</a></li>

            <li><a href="treatment.php">TREATMENTS</a></li>

            <li><a href="enquiry.php">ENQUIRY</a></li>

            <li><a href="contact.php">CONTACT US</a></li>

            <li>
                <a href="book.php" class="btn-book">
                    BOOK AN APPOINTMENT
                </a>
            </li>

        </ul>
    </nav>

</div>

</header>
<div class="header-divider"></div>