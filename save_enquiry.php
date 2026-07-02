<?php
include 'includes/db.php';

if(isset($_POST['submit'])){

    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
   $condition = $_POST['condition_name'];
    $message = $_POST['message'];

    $sql = "INSERT INTO enquiry(name,phone,email,condition_name,message)
            VALUES('$name','$phone','$email','$condition','$message')";

    if(mysqli_query($conn,$sql)){
        echo "<script>
        alert('Enquiry Submitted Successfully!');
        window.location='enquiry.php';
        </script>";
    }else{
        echo "Error: " . mysqli_error($conn);
    }
}
?>