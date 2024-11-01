// Settings from payment gateway
const API_KEY = pay_theory_plugin.API_KEY;
const EMAIL_RECEIPT = pay_theory_plugin.EMAIL_RECEIPT === "yes";

let cardNumberValid = false;
let cardExpValid = false;
let cardCvvValid = false;

let cleanupStateObserver = null;
let cleanupErrorObserver = null;

// Special variable to help with the flow
let processPaymentCounter= 0;

// Custom styles
let STYLES = {
    default: {
        color: 'black',
        fontSize: '14px',
        paddingLeft: "8px"
    },
    hidePlaceholder: true
}

// Hide WooCommerce spinner
function hideWooCommerceSpinner() {
    jQuery(function ($){
        $('.woocommerce-checkout-payment').unblock();
    })
}

// Show WooCommerce spinner
function showWooCommerceSpinner() {
    jQuery(function($){
        $('.woocommerce-checkout-payment').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    });
}

function showPayTheoryError(message) {
    jQuery(function ($){
        $('#pt-woo-error-message').html(message)
        $('#pt-woo-error-message').css("display", "block")
        $( 'html, body' ).animate({
            scrollTop: ( $( '#pt-woo-error-message' ).offset().top - 200 )
        }, 500 );

    })
}

function hidePayTheoryError() {
    jQuery(function ($){
        $('#pt-woo-error-message').css("display", "none")
    })
}

// Function to payment gateway with success callback
function callSuccessCallback(result) {
    return new Promise(function(resolve) {
        sendData(result, "success_callback")
            .then(response => {
                resolve(response)
            })
            .catch(error => {
                resolve(error)
            });
    });
}

function retrieveOrderComments() {
    const order_comments_element = document.getElementById('order_comments');
    if (order_comments_element && order_comments_element.value) {
        return order_comments_element.value;
    } else {
        return '';
    }
}

function validateBillingInformation() {
    const billing_first_name = document.getElementById("billing_first_name").value;
    const billing_last_name = document.getElementById("billing_last_name").value;
    const billing_address_1 = document.getElementById("billing_address_1").value;
    const billing_address_2 = document.getElementById("billing_address_2").value;
    const billing_city = document.getElementById("billing_city").value;
    const billing_state = document.getElementById("billing_state").value;
    const billing_postcode = document.getElementById("billing_postcode").value;
    const billing_phone = document.getElementById("billing_phone").value;
    const billing_email = document.getElementById("billing_email").value;
    const billingInfo = {
        billing_first_name,
        billing_last_name,
        billing_address_1,
        billing_address_2,
        billing_city,
        billing_state,
        billing_postcode,
        billing_phone,
        billing_email
    };
    let isValid = true;

    if (billing_first_name === null || billing_first_name === "") {
        showPayTheoryError("Please add billing first name");
        isValid = false;
    }
    else if (billing_last_name === null || billing_last_name === "") {
        showPayTheoryError("Please add billing last name");
        isValid = false;
    }
    else if (billing_address_1 === null || billing_address_1 === "") {
        showPayTheoryError("Please add billing street address");
        isValid = false;
    }
    else if (billing_city === null || billing_city === "") {
        showPayTheoryError("Please add billing city");
        isValid = false;
    }
    else if (billing_state === null || billing_state === "") {
        showPayTheoryError("Please add billing state");
        isValid = false;
    }
    else if (billing_postcode === null || billing_postcode === "") {
        showPayTheoryError("Please add billing zip");
        isValid = false;
    }
    else if (billing_phone === null || billing_phone === "") {
        showPayTheoryError("Please add billing phone number");
        isValid = false;
    }
    else if (billing_email === null || billing_email === "") {
        showPayTheoryError("Please add billing email address");
        isValid = false;
    }
    // Get payment amount
    const orderTotal = document.querySelector('.order-total .amount.woocommerce-Price-amount');

    if (orderTotal) {
        const value = orderTotal.textContent;
        billingInfo.amount = parseInt(value.replace(/\D/g, '')); // removes non-numeric characters
    }
    else {
        isValid = false;
    }

    return {isValid, billingInfo};
}

function retrievePayorInfo(billingInfo) {
    const checkbox = document.querySelector('input#ship-to-different-address-checkbox');
    if (checkbox !== null && checkbox.checked) {
        return {
            "first_name": document.getElementById("shipping_first_name").value,
            "last_name": document.getElementById("shipping_last_name").value,
            "email": billingInfo.billing_email,
            "phone": billingInfo.billing_phone,
            "personal_address": {
                "line1": document.getElementById("shipping_address_1").value,
                "line2": document.getElementById("shipping_address_2").value,
                "city": document.getElementById("shipping_city").value,
                "region": document.getElementById("shipping_state").value,
                "postal_code": document.getElementById("shipping_postcode").value
            }
        }
    }
    return {
        "first_name": billingInfo.billing_first_name,
        "last_name": billingInfo.billing_last_name,
        "email": billingInfo.billing_email,
        "phone": billingInfo.billing_phone,
        "personal_address": {
            "city": billingInfo.billing_city,
            "region": billingInfo.billing_state,
            "line1": billingInfo.billing_address_1,
            "line2": billingInfo.billing_address_2,
            "postal_code": billingInfo.billing_postcode
        }
    }
}

// Process before the submitting WC forms
const prepareForCheckout = function ()
{
    jQuery(function ($)
    {
        // Retrieve checkout form and turn off the prepareForCheckout from triggering again
        const checkout_form = $('form.checkout.woocommerce-checkout');
        checkout_form.off('checkout_place_order', prepareForCheckout);
    });

    //Check billing info and throw error if it's not available
    const {isValid, billingInfo} = validateBillingInformation();

    if (isValid)
    {
        // Process payment
        processPayment(billingInfo).then()
    }
    else
    {
        processPaymentCounter--;
    }
};

async function processPayment(billingInfo) {
    showWooCommerceSpinner()
    hidePayTheoryError()

    // Retrieve order comments if available
    const orderComments = retrieveOrderComments()

    // Retrieve shipping info if form is available. Will return false if same as billing.
    const PAYOR_INFO = retrievePayorInfo(billingInfo)
    const BILLING_INFO = {
        name: billingInfo.billing_first_name + " " + billingInfo.billing_last_name,
        address: {
            "city": billingInfo.billing_city,
            "region": billingInfo.billing_state,
            "line1": billingInfo.billing_address_1,
            "line2": billingInfo.billing_address_2,
            "postal_code": billingInfo.billing_postcode
        }
    }

    const TRANSACTING_PARAMETERS = {
        amount: billingInfo.amount,
        payorInfo: PAYOR_INFO,
        billingInfo: BILLING_INFO,
        sendReceipt: EMAIL_RECEIPT
    }

    try {
        const result = await paytheory.transact(TRANSACTING_PARAMETERS);

        // If success callback to php gateway to store payment details
        if (result['type'] === "SUCCESS") {
            // send payment response to php success callback
            await callSuccessCallback(result);
            hideWooCommerceSpinner()
            // Submit for woocommerce process_payment
            jQuery(function($){
                const checkout_form = $('form.checkout.woocommerce-checkout');
                checkout_form.trigger( "submit" );
            });
        } else if (result['type'] === "FAILED"){
            processPaymentCounter--;
            hideWooCommerceSpinner()
            showPayTheoryError("Your payment was declined. Please try again.")
        } else if (result['type'] === "ERROR")
        {
            processPaymentCounter--;
            hideWooCommerceSpinner()
            showPayTheoryError("There was an error trying to process your payment. Please try again.")
        } else {
            processPaymentCounter--;
            hideWooCommerceSpinner()
        }
        return result;
    } catch (error) {
        console.log(`system error: ${JSON.stringify(error)}`);
    }
}

// Network call to PHP Payment gateway callbackName
async function sendData(dataBody, callbackName) {
    const currentDomain = window.location.href.split(".com")[0] + ".com";
    const response = await fetch(`${currentDomain}/wordpress/index.php/wc-api/${callbackName}`, {
        method: "POST",
        body: JSON.stringify(dataBody),
        headers: {
            "Content-Type": "application/json"
        }
    });
    return dataBody;
}

// Disable WC submitting form
jQuery(function($) {
    // WooCommerce checkout form
    const checkout_form = $('form.checkout');

    checkout_form.on('checkout_place_order', function (x)
    {
        processPaymentCounter++;
        if (processPaymentCounter > 1)
        {
            processPaymentCounter--;
            return true;
        }

        const paymentBox = document.getElementsByClassName("payment_box payment_method_paytheory")[0];
        const display = paymentBox.style.display;
        if (display !== "none")
        {
            // This will only run if Pay Theory is chosen as the payment method
            if (!cardNumberValid)
            {
                processPaymentCounter--;
                showPayTheoryError("Card Number is not valid")
                return false; // This will prevent the form submission
            }
            else if (!cardExpValid)
            {
                processPaymentCounter--;
                showPayTheoryError("Card Expiration is not valid")
                return false; // This will prevent the form submission
            }
            else if (!cardCvvValid)
            {
                processPaymentCounter--;
                showPayTheoryError("Card CVV is not valid")
                return false; // This will prevent the form submission
            }
            else
            {
                prepareForCheckout();
                return false; // This will prevent the form submission
            }
        }
        processPaymentCounter--;

        return true
    });
});

function initializePayTheoryFields() {
    if (cleanupStateObserver === null)
    {
        cleanupStateObserver = paytheory.stateObserver(function (state)
        {
            // Tracking if the three pay theory fields are valid so we can track for validation on click
            cardCvvValid = !!(state["card-cvv"] && state["card-cvv"].errorMessages?.length === 0 && state["card-cvv"].isDirty);
            cardExpValid = !!(state["card-exp"] && state["card-exp"].errorMessages?.length === 0 && state["card-exp"].isDirty);
            cardNumberValid = !!(state["card-number"] && state["card-number"].errorMessages?.length === 0 && state["card-number"].isDirty);
        });
    }

    if (cleanupErrorObserver === null)
    {
        cleanupErrorObserver = paytheory.errorObserver(function (error)
        {
            if (error.startsWith("SESSION_EXPIRED"))
            {
                // If session expired, show error asking user to refresh page
                showPayTheoryError("Payment Session Expired. Please refresh page to continue check out.")
            }
        });
    }

    paytheory.payTheoryFields({
        apiKey: API_KEY,
        styles: STYLES
    });
}

// Initialize paytheory fields only once after ajax requests
jQuery(document).ajaxComplete(function() {
    initializePayTheoryFields();
});