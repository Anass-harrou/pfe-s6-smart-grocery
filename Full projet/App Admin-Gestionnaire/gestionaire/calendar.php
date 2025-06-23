<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <style>
    body {
       background-color:rgb(200, 229, 247);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .container {
        
        max-width: 1000px;
         margin-left: 450px; /* same width as sidebar */
    padding: 2rem;
    }

    

    
            .sidebar {
            background-color: rgb(86, 117, 148);
            color: white;
            padding: 1rem;
            width: 250px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1010;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand img {
            max-height: 3rem;
            margin-right: 0.75rem;
        }

        .sidebar-brand {
            max-height: 60rem;
    margin-right: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center; /* centers horizontally */
    padding: 1rem 0;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

        .sidebar-nav {
            flex-grow: 1;
            padding-top: 1rem;
        }

        .nav-item {
            margin-bottom: 0.75rem;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            transition: background-color 0.15s ease-in-out;
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer {
            padding: 1rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
</style>

   
</head>
<body>
    
    
        <div class="sidebar">
                 <div class="lsiderbar-brand" style="display: flex; justify-content: center; align-items: center;">
    <img src="../tablet/images/logo.png" alt="logo" style="width: 120px;">
</div>
        <!-- hi -->

<!-- hna -->
        <hr class="bg-light">

        <div class="sidebar-nav">

               <div class="nav-item">
                <a class="nav-link" href="dashboard.php">
                   <i class="fas fa-table"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link" href="stock.php">
                    <i class="fas fa-box me-2"></i>
                    Stock
                </a>
            </div>

            <div class="nav-item">
                <a class="nav-link" href="ajouterproduit.php">
                    <i class="fas fa-plus me-2"></i>
                    Ajouter produit
                </a>
            </div>
            
            <div class="nav-item">
                <a class="nav-link" href="rapport.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Rapport
        </a>
            </div>

            <div class="nav-item">
                <a class="nav-link" href="reception.php">
                    <i class="fas fa-bell me-2"></i>
                    Réception
                </a>
            </div>
            
<div class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="fas fa-calendar me-2"></i>
                   calendar
                </a>
            </div>

        </div>
        <div class="sidebar-footer">
            <hr class="bg-light">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Déconnexion
            </a>
        </div>
    </div>


  <div class="container">
    <iframe src="https://calendar.google.com/calendar/embed?src=temsamaniflower123%40gmail.com&ctz=Africa%2FCasablanca" style="border: 0" width="800" height="600" frameborder="0" scrolling="no"></iframe>
  </div>
</body>
</html>