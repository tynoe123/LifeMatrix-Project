import pandas as pd
import numpy as np
import tensorflow as tf
from tensorflow import keras
from tensorflow.keras import layers
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.utils.class_weight import compute_class_weight
import joblib
import json

# =========================
# LOAD TRAINING DATA
# =========================
# CSV columns expected:
# avg_hr,max_hr,avg_spo2,min_spo2,avg_temp,max_temp,fall_count,
# high_hr_count,low_spo2_count,high_temp_count,label

df = pd.read_csv("patient_training_data.csv")

feature_cols = [
    "avg_hr",
    "max_hr",
    "avg_spo2",
    "min_spo2",
    "avg_temp",
    "max_temp",
    "fall_count",
    "high_hr_count",
    "low_spo2_count",
    "high_temp_count"
]

X = df[feature_cols].astype(float).values
y_labels = df["label"].astype(str)

class_names = sorted(y_labels.unique().tolist())
class_to_idx = {name: i for i, name in enumerate(class_names)}
idx_to_class = {i: name for name, i in class_to_idx.items()}

y = np.array([class_to_idx[label] for label in y_labels])

# =========================
# SCALE FEATURES
# =========================
scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)

X_train, X_val, y_train, y_val = train_test_split(
    X_scaled, y, test_size=0.2, random_state=42, stratify=y
)

# =========================
# HANDLE CLASS IMBALANCE
# =========================
classes = np.unique(y_train)
class_weights = compute_class_weight(
    class_weight="balanced",
    classes=classes,
    y=y_train
)
class_weight_dict = {int(c): float(w) for c, w in zip(classes, class_weights)}

# =========================
# BUILD MODEL
# =========================
model = keras.Sequential([
    layers.Input(shape=(len(feature_cols),)),
    layers.Dense(32, activation="relu"),
    layers.Dropout(0.2),
    layers.Dense(16, activation="relu"),
    layers.Dropout(0.1),
    layers.Dense(len(class_names), activation="softmax")
])

model.compile(
    optimizer="adam",
    loss="sparse_categorical_crossentropy",
    metrics=["accuracy"]
)

early_stop = keras.callbacks.EarlyStopping(
    monitor="val_loss",
    patience=15,
    restore_best_weights=True
)

history = model.fit(
    X_train,
    y_train,
    validation_data=(X_val, y_val),
    epochs=200,
    batch_size=16,
    class_weight=class_weight_dict,
    callbacks=[early_stop],
    verbose=1
)

# =========================
# SAVE MODEL + SCALER + LABEL MAP
# =========================
model.save("patient_risk_model.h5")
joblib.dump(scaler, "patient_scaler.save")

with open("class_names.json", "w") as f:
    json.dump(class_names, f)

print("Training complete.")
print("Saved:")
print("- patient_risk_model.h5")
print("- patient_scaler.save")
print("- class_names.json")