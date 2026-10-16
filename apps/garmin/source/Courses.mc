import Toybox.Application;
import Toybox.Lang;
import Toybox.PersistedContent;

// The device's own Courses, and which of them this app saved for which route.
// A route is known by its download path, so two routes with the same name stay apart.
// Courses saved before the app kept that link are found by name.
module Courses {
    // Every course on the device by its id, and the newest one for each name.
    function index() {
        var ids = {};
        var names = {};

        if (Toybox has :PersistedContent) {
            var courses = PersistedContent.getCourses();
            var item = courses.next();

            while (item != null) {
                ids[item.getId()] = item;
                names[item.getName()] = item;
                item = courses.next();
            }
        }

        return { :ids => ids, :names => names, :links => links() };
    }

    // The course on the device for this route, or null.
    function find(known, entry) {
        var links = known[:links];
        var id = links[entry[:path]];

        if (id != null && known[:ids].hasKey(id)) {
            return known[:ids][id];
        }

        // By name, unless that course belongs to another route.
        var course = known[:names][entry[:name]];

        if (course == null || id != null) {
            return null;
        }

        var owners = links.values();

        for (var index = 0; index < owners.size(); index++) {
            if (owners[index] == course.getId()) {
                return null;
            }
        }

        return course;
    }

    function remember(entry, course) {
        var links = links();
        links[entry[:path]] = course.getId();
        Application.Storage.setValue("courses", links);
    }

    function forget(entry) {
        var links = links();
        links.remove(entry[:path]);
        Application.Storage.setValue("courses", links);
    }

    function links() {
        var stored = Application.Storage.getValue("courses");

        return stored instanceof Dictionary ? stored : {};
    }
}
