<?php
$pageTitle="Book Appointment | Bodhi Homeopathy";
include 'includes/header.php';
?>

<section class="pro-contact-hero">
<div class="hero-overlay-dark">
<div class="container">

<h1>Book Appointment</h1>

<p>Schedule your consultation.</p>

</div>
</div>
</section>

<section class="booking-main-section">

<div class="container">

<form action="save_appointment.php" method="POST" class="booking-actual-form">

<div class="form-pro-grid">

<div class="field-pro-group">
<label>Patient Name</label>
<input type="text" name="name" required>
</div>

<div class="field-pro-group">
<label>Phone</label>
<input type="tel" name="phone" required>
</div>

</div>

<div class="form-pro-grid">

<div class="field-pro-group">
<label>Date</label>
<input type="date" name="date" min="<?php echo date('Y-m-d'); ?>" required>
</div>

<div class="field-pro-group">

<label>Mode</label>

<select name="mode">

<option>Direct Clinic Visit</option>

<option>Online Consultation</option>

</select>

</div>

</div>

<div class="field-pro-group">

<label>Category</label>

<select name="category">

<option>General Health</option>

<option>Thyroid</option>

<option>Skin Care</option>

<option>Arthritis</option>

<option>Pediatric</option>

</select>

</div>

<div class="field-pro-group">

<label>Notes</label>

<textarea name="notes" rows="5"></textarea>

</div>

<button type="submit" name="submit" class="booking-confirm-btn">

BOOK APPOINTMENT

</button>

</form>

</div>

</section>

<?php include 'includes/footer.php'; ?>