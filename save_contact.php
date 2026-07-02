<?php
include 'includes/db.php';

if(isset($_POST['submit'])){

    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    $sql = "INSERT INTO contact(name,email,subject,message)
            VALUES('$name','$email','$subject','$message')";

    if(mysqli_query($conn,$sql)){
        echo "<script>
        alert('Message Sent Successfully!');
        window.location='contact.php';
        </script>";
    }else{
        echo "Error: " . mysqli_error($conn);
    }
}
?>