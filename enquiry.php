<?php
$pageTitle="General Enquiry | Bodhi Homeopathy";
include 'includes/header.php';
?>

<section class="enquiry-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>General Enquiry</h1>
            <p>Providing expert homeopathic solutions.</p>
        </div>
    </div>
</section>

<section class="enquiry-content">

<div class="container">

<form action="save_enquiry.php" method="POST" class="booking-actual-form">

<div class="booking-heading">
<h2>General Enquiry</h2>
<p>Have a question? Fill out the form below and our team will get back to you shortly.</p>
</div>

<div class="form-pro-grid">

<div class="field-pro-group">
<label>Name</label>
<input type="text" name="name" required>
</div>

<div class="field-pro-group">
<label>Phone</label>
<input type="tel" name="phone" required>
</div>

</div>

<div class="field-pro-group">
<label>Email</label>
<input type="email" name="email">
</div>

<div class="field-pro-group">

<label>Condition</label>

<select name="condition_name">

<option selected disabled>Select an option</option>

<option value="Thyroid">Thyroid</option>

<option value="Diabetes">Diabetes</option>

<option value="Arthritis">Arthritis</option>

<option value="Skin Care">Skin Care</option>

<option value="General Health">General Health</option>

</select>

</div>

<div class="field-pro-group">

<label>Message</label>

<textarea name="message" rows="6"></textarea>

</div>

<button type="submit" name="submit" class="booking-confirm-btn">

SUBMIT ENQUIRY

</button>

</form>

</div>

</section>

<?php include 'includes/footer.php'; ?>