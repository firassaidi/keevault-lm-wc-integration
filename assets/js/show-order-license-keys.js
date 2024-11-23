jQuery(document).ready(function ($) {
    const container = $('#license-key-details-container');

    // Make AJAX request to fetch license key details
    $.post(licenseKeyDetailsData.ajax_url, {
        action: 'get_license_key_details',
        order_id: licenseKeyDetailsData.order_id
    })
        .done(function (response) {
            if (response.success) {
                // Start building the HTML table with WooCommerce styling
                let licenseKeyHtml = `
                <table class="shop_table woocommerce-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: left;">${licenseKeyDetailsData.license_key}</th>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: left;">${licenseKeyDetailsData.activation_limit}</th>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: left;">${licenseKeyDetailsData.validity}</th>
                            <th class="woocommerce-table__header" style="padding: 12px; text-align: center;">${licenseKeyDetailsData.license_key_status}</th>
                        </tr>
                    </thead>
                    <tbody class="woocommerce-table__body">`;

                // Add a table row for each licenseKey
                response.data.forEach(licenseKey => {
                    let validity = licenseKeyDetailsData.unlimited;

                    if (parseInt(licenseKey.validity) > 0) {
                        validity = licenseKey.validity + ' ' + licenseKeyDetailsData.days;
                    }

                    licenseKeyHtml += `
                    <tr class="woocommerce-table__row">
                        <td class="woocommerce-table__cell" style="padding: 12px;">${licenseKey.license_key}</td>
                        <td class="woocommerce-table__cell" style="padding: 12px;">${licenseKey.activation_limit}</td>
                        <td class="woocommerce-table__cell" style="padding: 12px;">${validity}</td>
                        <td class="woocommerce-table__cell" style="padding: 12px; text-align: center; text-transform: capitalize">${licenseKey.status}</td>
                    </tr>`;
                });

                // Close the table
                licenseKeyHtml += `
                    </tbody>
                </table>`;

                // Insert the table into the container
                container.html(licenseKeyHtml);
            } else {
                container.html(`<p>${licenseKeyDetailsData.no_license_keys_found}</p>`);
            }
        })
        .fail(function () {
            container.html(`<p>${licenseKeyDetailsData.failed_to_load}</p>`);
        });
});
