package com.example;

import com.google.gson.Gson;
import spark.Request;
import spark.Response;
import spark.Spark;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

class Event {
    private String name;
    private Map<String, Object> payload;
    private String uuid;
    private int autoIncrementingId;
    private int aggregateId;
    private int aggregateVersion;
    private long occurredAt;
    private String correlationId;
    private String causationEventUUID;

    public Event(String name, Map<String, Object> payload, String uuid, int autoIncrementingId, int aggregateId,
                 int aggregateVersion, long occurredAt, String correlationId, String causationEventUUID) {
        this.name = name;
        this.payload = payload;
        this.uuid = uuid;
        this.autoIncrementingId = autoIncrementingId;
        this.aggregateId = aggregateId;
        this.aggregateVersion = aggregateVersion;
        this.occurredAt = occurredAt;
        this.correlationId = correlationId;
        this.causationEventUUID = causationEventUUID;

        // Ensure no fields are missing
        if (name == null || payload == null || uuid == null || occurredAt == 0) {
            throw new IllegalArgumentException("Missing field");
        }
    }

    public String getName() {
        return name;
    }

    public Map<String, Object> getPayload() {
        return payload;
    }
}

class Store {
    private List<Event> events = new ArrayList<>();
    private Map<String, List<EventListener>> listeners = new HashMap<>();

    public void commit(Event event) {
        events.add(event);
        List<EventListener> eventListeners = listeners.getOrDefault(event.getName(), new ArrayList<>());
        for (EventListener listener : eventListeners) {
            listener.handle(event);
        }
    }

    public void subscribe(String eventName, EventListener listener) {
        listeners.computeIfAbsent(eventName, k -> new ArrayList<>()).add(listener);
    }

    public List<Event> getEvents() {
        return events;
    }
}

interface EventListener {
    void handle(Event event);
}

public class Main {

    private static Store store = new Store();
    private static Map<String, String> UsersDB = new HashMap<>();

    public static void main(String[] args) {
        Gson gson = new Gson();
        Spark.port(8080);

        // Subscribe to the "SignUp" event
        store.subscribe("SignUp", event -> {
            Map<String, Object> payload = event.getPayload();
            String username = (String) payload.get("username");
            String password = (String) payload.get("password");
            UsersDB.put(username, password);
        });

        // Home Route
        Spark.get("/", (req, res) -> {
            Map<String, Object> content = new HashMap<>();
            content.put("events", store.getEvents());
            content.put("usersDB", UsersDB);
            res.type("application/json");
            return gson.toJson(content);
        });

        // Sign-up Route
        Spark.post("/sign-up", (req, res) -> {
            Map<String, Object> requestBody = gson.fromJson(req.body(), Map.class);
            String username = (String) requestBody.get("username");
            String password = (String) requestBody.get("password");

            // Create the sign-up command
            Map<String, Object> command = new HashMap<>();
            command.put("username", username);
            command.put("password", password);

            // Create and commit the event
            Event event = new Event(
                    "SignUp",
                    command,
                    String.valueOf(System.currentTimeMillis() + Math.random()),
                    2,
                    1,
                    1,
                    System.currentTimeMillis(),
                    null,
                    null
            );
            store.commit(event);

            res.status(200);
            return "Signed up!\n";
        });

        // Sign-in Route
        Spark.post("/sign-in", (req, res) -> {
            Map<String, Object> requestBody = gson.fromJson(req.body(), Map.class);
            String username = (String) requestBody.get("username");
            String password = (String) requestBody.get("password");

            // Validate credentials
            if (UsersDB.containsKey(username) && UsersDB.get(username).equals(password)) {
                return "Logged in!";
            } else {
                return "Invalid credentials";
            }
        });
    }
}
