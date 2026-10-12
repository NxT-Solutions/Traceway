import Toybox.Application;
import Toybox.Communications;
import Toybox.Lang;

// Requests go out through the phone. The token comes from pairing and stays in app storage.
module Api {
    function base() {
        var server = Application.Properties.getValue("server");

        if (server instanceof String && server.length() > 0) {
            return server;
        }

        return "https://traceway.online";
    }

    function host() {
        var url = base();
        var start = url.find("://");

        return start == null ? url : url.substring(start + 3, url.length());
    }

    function token() {
        return Application.Storage.getValue("token");
    }

    function remember(token) {
        Application.Storage.setValue("token", token);
    }

    function forget() {
        Application.Storage.deleteValue("token");
    }

    function post(path, body, callback) {
        Communications.makeWebRequest(base() + path, body, {
            :method => Communications.HTTP_REQUEST_METHOD_POST,
            :headers => {
                "Content-Type" => Communications.REQUEST_CONTENT_TYPE_JSON,
                "Accept" => "application/json"
            },
            :responseType => Communications.HTTP_RESPONSE_CONTENT_TYPE_JSON
        }, callback);
    }

    function get(path, callback) {
        Communications.makeWebRequest(base() + path, null, {
            :method => Communications.HTTP_REQUEST_METHOD_GET,
            :headers => {
                "Authorization" => "Bearer " + token(),
                "Accept" => "application/json"
            },
            :responseType => Communications.HTTP_RESPONSE_CONTENT_TYPE_JSON
        }, callback);
    }

    // The device parses the FIT file and stores it as a course on its own.
    function course(path, callback) {
        Communications.makeWebRequest(base() + path, null, {
            :method => Communications.HTTP_REQUEST_METHOD_GET,
            :headers => {
                "Authorization" => "Bearer " + token()
            },
            :responseType => Communications.HTTP_RESPONSE_CONTENT_TYPE_FIT
        }, callback);
    }
}
