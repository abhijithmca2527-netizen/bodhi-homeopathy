<?php
$pageTitle = "Contact Us | Bodhi Homeopathy";
include 'includes/header.php';
?>

<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <span class="sub-tag">GET IN TOUCH</span>
            <h1>Contact Us</h1>
            <p>Healing naturally with a legacy of trust. Our team is ready to assist you.</p>
        </div>
    </div>
</section>

<section class="info-card-section">
    <div class="container">
        <div class="modern-contact-grid">

            <div class="info-card-pro">
                <div class="icon-circle-bg"><i class="fas fa-map-marked-alt"></i></div>
                <h3>Our Location</h3>
                <p>Bodhi Homeopathic Speciality Hospital,<br>Aban Jn. Pathanamthitta, Kerala.</p>
                <a href="https://maps.app.goo.gl/FftZKDjifm4UDchr9" class="map-link">Open in Maps</a>
            </div>

            <div class="info-card-pro">
                <div class="icon-circle-bg"><i class="fas fa-headset"></i></div>
                <h3>Call Us</h3>
                <p>+91 9656407810<br>0484 232 1999</p>
            </div>

            <div class="info-card-pro">
                <div class="icon-circle-bg"><i class="fas fa-envelope-open-text"></i></div>
                <h3>Email Us</h3>
                <p>bodhihomeopathy@gmail.com</p>
            </div>

        </div>
    </div>
</section>

<section class="premium-form-section">
<div class="container">
<div class="form-wrapper-pro">

<div class="form-side-info">
<h2>Send Us a Message</h2>
<div class="accent-orange-line"></div>
<p>Provide your details and we will get back to you.</p>
</div>

<form action="save_contact.php" method="POST" class="actual-pro-form">

<div class="form-row-pro">

<div class="field-group">
<label>Your Name</label>
<input type="text" name="name" placeholder="Your Name" required>
</div>

<div class="field-group">
<label>Your Email</label>
<input type="email" name="email" placeholder="Email" required>
</div>

</div>

<div class="field-group">
<label>Subject</label>
<input type="text" name="subject" placeholder="Subject" required>
</div>

<div class="field-group">
<label>Message</label>
<textarea name="message" rows="5" required></textarea>
</div>

<button type="submit" name="submit" class="submit-pro-btn">
SEND MESSAGE
</button>

</form>

</div>
</div>
</section>

<?php include 'includes/footer.php'; ?>