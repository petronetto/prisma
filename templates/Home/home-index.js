/**
 * Class
 */
var HomeIndex = function () {

    // The current object scope
    var $this = this;

    /**
     * Init
     *
     * @returns {boolean}
     */
    this.init = function () {
        return true;
    };

    /**
     * Load content
     *
     * @returns {undefined}
     */
    this.load = function () {
        $d.showLoad();

        var params = {
            username: "max",
            email: "max@example.com"
        };

        ajax.post($d.getBaseUrl("home/load"), params).done(function (response) {
            $d.hideLoad();
            $d.log(response);
            $d.notify({
                msg: "<b>Ok</b> " + response.message,
                type: "success",
                position: "center"
            });

            // Translations
            response.text = {
                'current_user': __('Current user'),
                'user_id': __('User-ID'),
                'username': __('Username'),
                'its': __('Its'),
            };

            var template = $('#user-template').html();
            //Mustache.parse(template);
            var output = Mustache.render(template, response);

            $('#content').append(output);

        }).fail(function (error) {
            if (error.status == 422) {
                $d.hideLoad();
                // Show validation errors
                var response = error.responseJSON;
                $d.alert(response.error.message);
                $(response.error.errors).each(function (i, error) {
                    console.log("Error in field [" + error.field + "]: " + error.message);
                });
            } else {
                ajax.handleError(error);
            }
        });
    };

    this.init();
};

$(function () {
    (new HomeIndex()).load();
});
