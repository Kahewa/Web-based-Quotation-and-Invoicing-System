<?php

include "head.php";

?>

<div class="nav">
    <?php
    
    if (isset($_SESSION["name"])){ 
        //do this when user is logged in
        $name = $_SESSION["name"];
        echo "Logged in as ".$name." ";
        echo "<a href='logout.php'><button>Logout</button></a>";


    } else{
        //do this when user is not logged in
    echo "<a href='login.php'><button>User Login</button></a>";
    echo '<a href="user_reg.php"><button>User Registration</button></a>';
    }
    ?>
</div>