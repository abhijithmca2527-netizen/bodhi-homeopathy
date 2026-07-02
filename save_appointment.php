<?php
include 'includes/db.php';

if(isset($_POST['submit'])){

    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $date = $_POST['date'];
    $mode = $_POST['mode'];
    $category = $_POST['category'];
    $notes = $_POST['notes'];

    $sql = "INSERT INTO appointment(patient_name,phone,appointment_date,mode,category,notes)
            VALUES('$name','$phone','$date','$mode','$category','$notes')";

    if(mysqli_query($conn,$sql)){
        echo "<script>
        alert('Appointment Booked Successfully!');
        window.location='book.php';
        </script>";
    }else{
        echo "Error: " . mysqli_error($conn);
    }
}
?>