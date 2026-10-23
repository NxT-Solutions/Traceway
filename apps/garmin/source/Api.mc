import Toybox.Application;
import Toybox.Communications;
import Toybox.Lang;
import Toybox.PersistedContent;

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

    // Tells Traceway this device is unlinked, so it leaves Settings, then Devices. The answer does not matter: the token
    // is forgotten here either way, and a device that could not reach the phone can still be removed in Settings.
    function unlink() {
        Communications.makeWebRequest(base() + "/api/device/link", null, {
            :method => Communications.HTTP_REQUEST_METHOD_DELETE,
            :headers => {
                "Authorization" => "Bearer " + token(),
                "Accept" => "application/json"
            },
            :responseType => Communications.HTTP_RESPONSE_CONTENT_TYPE_JSON
        }, new Lang.Method(Api, :ignore));
        forget();
    }

    function ignore(code as Number, data as Dictionary or String or PersistedContent.Iterator or Null) as Void {
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
