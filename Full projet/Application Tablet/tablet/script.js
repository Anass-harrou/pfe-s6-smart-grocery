var loginModal = document.getElementById("loginModal");
    var registerModal = document.getElementById("registerModal");
    var loginLink = document.getElementById("loginLink");
    var closeLoginModal = document.getElementById("closeLoginModal");
    var closeRegisterModal = document.getElementById("closeRegisterModal");

    loginLink.onclick = function() {
        <?php if ($isLoggedIn): ?>
        window.location.href = "index.php?logout=true";
        <?php else: ?>
        loginModal.style.display = "block";
        <?php endif; ?>
    }

    closeLoginModal.onclick = function() {
        loginModal.style.display = "none";
    }

    closeRegisterModal.onclick = function() {
        registerModal.style.display = "none";
    }

    function openRegisterModal() {
        registerModal.style.display = "block";
    }

    window.onclick = function(event) {
        if (event.target == loginModal) {
            loginModal.style.display = "none";
        }
        if (event.target == registerModal) {
            registerModal.style.display = "none";
        }
    }

    $(document).ready(function() {
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
            "timeOut": "3000",
            "extendedTimeOut": "1000"
        };

        function updateCartDisplay() {
            $.ajax({
                url: 'index.php?update_cart_display=1&_=' + new Date().getTime(),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#cart-content').html(response.cart_html);
                    
                    if (response.notification && response.notification_type) {
                        toastr[response.notification_type](response.notification);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                    toastr.error('Erreur lors de la mise à jour du panier');
                }
            });
        }

        function checkJsonAndAddToCart() {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {action: 'update_cart'},
                success: function() {
                    updateCartDisplay();
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                    toastr.error('Erreur lors de la mise à jour du panier');
                }
            });
        }

        checkJsonAndAddToCart();
        setInterval(checkJsonAndAddToCart, 2000);

        $(document).on('click', '.add-to-cart-btn', function() {
            var productId = $(this).data('product-id');
            $.post('index.php', {
                product_id: productId,
                action: 'add',
                quantity: 1
            }, function() {
                updateCartDisplay();
            });
        });
    });